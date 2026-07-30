package main

/*
 * epg-sync
 *
 * Merges and deduplicates all XMLTV files in a given directory into a single
 * epg.xml output file. Channels are deduped by ID (first file wins).
 * Programmes are deduped by channel ID + start time.
 *
 * Build:
 *   sudo go build -o epg-sync main.go && sudo mv epg-sync /usr/local/bin/epg-sync
 *
 * Usage:
 *   epg-sync --input /path/to/xmltv --output /path/to/epg.xml
 *
 * @since   2.0
 * @author  Kevin Pirnie <me@kpirnie.com>
 * @package KPTV
 */

import (
	"bufio"
	"bytes"
	"encoding/xml"
	"flag"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"syscall"
	"time"
)

// ---------------------------------------------------------------------------
// CLI flags
// ---------------------------------------------------------------------------

// progKey is the dedup key for programmes
type progKey struct {
	Channel string
	Start   string
}

// multiFlag allows a flag to be specified multiple times
type multiFlag []string

func (m *multiFlag) String() string     { return strings.Join(*m, ",") }
func (m *multiFlag) Set(v string) error { *m = append(*m, v); return nil }

// ---------------------------------------------------------------------------
// XMLTV streaming merge
// ---------------------------------------------------------------------------

// mergeEPGFiles streams all source files into a single XMLTV output file.
// Deduplicates channels by ID and programmes by channel+start.
func mergeEPGFiles(srcFiles []string, outPath string) error {
	// Write to a temp file in the same directory for atomic rename
	dir := filepath.Dir(outPath)
	tmp, err := os.CreateTemp(dir, "epg-merge-*.xml.tmp")
	if err != nil {
		return err
	}
	tmpPath := tmp.Name()

	defer func() {
		// Clean up temp file on failure
		if _, err := os.Stat(tmpPath); err == nil {
			os.Remove(tmpPath)
		}
	}()

	seenChannels := make(map[string]struct{})
	seenProgs := make(map[progKey]struct{})

	// buffer output writes; the old per-element writes went straight to syscalls
	w := bufio.NewWriterSize(tmp, 1<<20)

	w.WriteString(`<?xml version="1.0" encoding="utf-8"?>` + "\n")
	w.WriteString(`<tv generator-info-name="KPTV EPG Sync">` + "\n")

	// Pass 1 — channels only (priority order: first file wins dedup)
	log.Printf("Merge pass 1: channels")
	for _, src := range srcFiles {
		if err := streamElements(src, "channel", func(raw []byte, attrs map[string]string) error {
			id := strings.ToLower(strings.TrimSpace(attrs["id"]))
			if id == "" {
				return nil
			}
			if _, ok := seenChannels[id]; ok {
				return nil
			}
			seenChannels[id] = struct{}{}
			w.WriteString("  ")
			w.Write(raw)
			w.WriteString("\n")
			return nil
		}); err != nil {
			log.Printf("Warning: channel pass error in %s: %v", src, err)
		}
	}

	// Pass 2 — programmes only
	log.Printf("Merge pass 2: programmes")
	for _, src := range srcFiles {
		if err := streamElements(src, "programme", func(raw []byte, attrs map[string]string) error {
			ch := strings.ToLower(strings.TrimSpace(attrs["channel"]))
			start := strings.TrimSpace(attrs["start"])
			if ch == "" || start == "" {
				return nil
			}
			// Only include programmes for channels we kept
			if _, ok := seenChannels[ch]; !ok {
				return nil
			}
			k := progKey{Channel: ch, Start: start}
			if _, ok := seenProgs[k]; ok {
				return nil
			}
			seenProgs[k] = struct{}{}
			w.WriteString("  ")
			w.Write(raw)
			w.WriteString("\n")
			return nil
		}); err != nil {
			log.Printf("Warning: programme pass error in %s: %v", src, err)
		}
	}

	w.WriteString("</tv>\n")

	// a buffered write error surfaces here; without this check a full disk
	// would silently produce a truncated epg.xml
	if err := w.Flush(); err != nil {
		tmp.Close()
		return err
	}

	if err := tmp.Close(); err != nil {
		return err
	}

	// match the parent directory's owner/group (best-effort; needs privilege if different)
	if fi, err := os.Stat(dir); err == nil {
		if st, ok := fi.Sys().(*syscall.Stat_t); ok {
			os.Chown(tmpPath, int(st.Uid), int(st.Gid))
		}
	}

	// ensure the output is world-readable (CreateTemp defaults to 0600)
	if err := os.Chmod(tmpPath, 0644); err != nil {
		return err
	}

	if err := os.Rename(tmpPath, outPath); err != nil {
		return err
	}

	return nil
}

// streamElements reads an XMLTV file and calls fn for each element matching tag.
// raw is the element's original bytes sliced straight from the file; attrs are
// its attributes. A decoder error is terminal for the file — the same error
// repeats forever from Token(), so we stop and let the caller move on.
func streamElements(path, tag string, fn func(raw []byte, attrs map[string]string) error) error {
	data, err := os.ReadFile(path)
	if err != nil {
		return err
	}

	dec := xml.NewDecoder(bytes.NewReader(data))
	dec.Strict = false

	for {
		// offset of the next token's first byte, captured before reading it
		pos := dec.InputOffset()

		tok, err := dec.Token()
		if err == io.EOF {
			break
		}
		if err != nil {
			return fmt.Errorf("XML syntax error: %w", err)
		}

		start, ok := tok.(xml.StartElement)
		if !ok || start.Name.Local != tag {
			continue
		}

		// Collect attributes
		attrs := make(map[string]string, len(start.Attr))
		for _, a := range start.Attr {
			attrs[a.Name.Local] = a.Value
		}

		// skip to the matching end tag, then slice the original bytes —
		// far cheaper than re-encoding tokens, and preserves them verbatim
		if err := dec.Skip(); err != nil {
			return fmt.Errorf("XML syntax error inside <%s>: %w", tag, err)
		}
		raw := bytes.TrimSpace(data[pos:dec.InputOffset()])

		if err := fn(raw, attrs); err != nil {
			return err
		}
	}

	return nil
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

func main() {
	var (
		inputPath  string
		outputPath string
		extraURLs  multiFlag
		userAgent  string
	)

	flag.Var(&extraURLs, "extra-url", "Remote XMLTV URL to include (repeatable)")
	flag.StringVar(&inputPath, "input", "", "Directory containing XMLTV files to merge (required)")
	flag.StringVar(&outputPath, "output", "", "Output path for merged epg.xml (required)")
	flag.StringVar(&userAgent, "user-agent", "", "Optional User-Agent header for remote downloads")
	flag.Parse()

	if inputPath == "" {
		log.Fatal("--input is required")
	}
	if outputPath == "" {
		log.Fatal("--output is required")
	}

	// Collect all .xml files from the input directory
	entries, err := os.ReadDir(inputPath)
	if err != nil {
		log.Fatalf("Failed to read input directory: %v", err)
	}

	var srcFiles []string
	for _, e := range entries {
		if e.IsDir() {
			continue
		}
		name := e.Name()
		if strings.EqualFold(filepath.Ext(name), ".xml") {
			srcFiles = append(srcFiles, filepath.Join(inputPath, name))
		}
	}

	if len(srcFiles) == 0 {
		log.Fatalf("No .xml files found in %s", inputPath)
	}

	log.Printf("Found %d XML file(s) in %s", len(srcFiles), inputPath)
	for _, f := range srcFiles {
		log.Printf("  %s", f)
	}

	for _, u := range extraURLs {
		path, err := downloadToTemp(u, filepath.Dir(outputPath), userAgent)
		if err != nil {
			log.Printf("Warning: failed to download %s: %v", u, err)
			continue
		}
		defer os.Remove(path)
		srcFiles = append(srcFiles, path)
		log.Printf("  (remote) %s", u)
	}

	start := time.Now()
	log.Printf("Merging %d source file(s)...", len(srcFiles))

	if err := mergeEPGFiles(srcFiles, outputPath); err != nil {
		log.Fatalf("Merge failed: %v", err)
	}

	log.Printf("Done in %.1fs — EPG written to %s", time.Since(start).Seconds(), outputPath)
}

// downloadToTemp fetches a remote XMLTV URL to a temp file and returns the path.
func downloadToTemp(url, dir, userAgent string) (string, error) {
	client := &http.Client{Timeout: 5 * time.Minute}
	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return "", err
	}
	if userAgent != "" {
		req.Header.Set("User-Agent", userAgent)
	}
	resp, err := client.Do(req)
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return "", fmt.Errorf("HTTP %d for %s", resp.StatusCode, url)
	}

	tmp, err := os.CreateTemp(dir, "epg-extra-*.xml")
	if err != nil {
		return "", err
	}

	n, err := io.Copy(tmp, resp.Body)
	if err != nil {
		tmp.Close()
		os.Remove(tmp.Name())
		return "", err
	}

	// a clean early close from the server passes io.Copy but leaves a
	// truncated file — that's what triggered the parse-loop incident
	if resp.ContentLength >= 0 && n != resp.ContentLength {
		tmp.Close()
		os.Remove(tmp.Name())
		return "", fmt.Errorf("truncated download: got %d of %d bytes", n, resp.ContentLength)
	}

	tmp.Close()
	return tmp.Name(), nil
}
