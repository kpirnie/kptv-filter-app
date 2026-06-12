package main

/*
 * epg-sync
 *
 * Merges and deduplicates all XMLTV files in a given directory into a single
 * epg.xml output file. Channels are deduped by ID (first file wins).
 * Programmes are deduped by channel ID + start time.
 *
 * Usage:
 *   epg-sync --input /path/to/xmltv --output /path/to/epg.xml
 *
 * @since   2.0
 * @author  Kevin Pirnie <me@kpirnie.com>
 * @package KPTV
 */

import (
	"encoding/xml"
	"flag"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"
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

	seenChannels := make(map[string]bool)
	seenProgs := make(map[progKey]bool)

	tmp.WriteString(`<?xml version="1.0" encoding="utf-8"?>` + "\n")
	tmp.WriteString(`<tv generator-info-name="KPTV EPG Sync">` + "\n")

	// Pass 1 — channels only (priority order: first file wins dedup)
	log.Printf("Merge pass 1: channels")
	for _, src := range srcFiles {
		if err := streamElements(src, "channel", func(raw []byte, attrs map[string]string) error {
			id := strings.ToLower(strings.TrimSpace(attrs["id"]))
			if id == "" || seenChannels[id] {
				return nil
			}
			seenChannels[id] = true
			tmp.WriteString("  ")
			tmp.Write(raw)
			tmp.WriteString("\n")
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
			if !seenChannels[ch] {
				return nil
			}
			k := progKey{Channel: ch, Start: start}
			if seenProgs[k] {
				return nil
			}
			seenProgs[k] = true
			tmp.WriteString("  ")
			tmp.Write(raw)
			tmp.WriteString("\n")
			return nil
		}); err != nil {
			log.Printf("Warning: programme pass error in %s: %v", src, err)
		}
	}

	tmp.WriteString("</tv>\n")

	if err := tmp.Close(); err != nil {
		return err
	}

	if err := os.Rename(tmpPath, outPath); err != nil {
		return err
	}

	return nil
}

// streamElements opens an XMLTV file and calls fn for each element matching tag.
// raw is the complete raw XML of the element; attrs are its attributes.
func streamElements(path, tag string, fn func(raw []byte, attrs map[string]string) error) error {
	f, err := os.Open(path)
	if err != nil {
		return err
	}
	defer f.Close()

	dec := xml.NewDecoder(f)
	dec.Strict = false

	for {
		tok, err := dec.Token()
		if err == io.EOF {
			break
		}
		if err != nil {
			log.Printf("XML token error in %s: %v", path, err)
			continue
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

		// Read the complete element as raw bytes
		raw, err := captureElement(dec, start)
		if err != nil {
			log.Printf("Capture error for <%s> in %s: %v", tag, path, err)
			continue
		}

		if err := fn(raw, attrs); err != nil {
			return err
		}
	}

	return nil
}

// captureElement re-serializes the element currently being read by dec
// (positioned just after the StartElement token) back to raw XML bytes.
func captureElement(dec *xml.Decoder, start xml.StartElement) ([]byte, error) {
	var buf strings.Builder
	enc := xml.NewEncoder(&buf)

	if err := enc.EncodeToken(start); err != nil {
		return nil, err
	}

	depth := 1
	for depth > 0 {
		tok, err := dec.Token()
		if err != nil {
			return nil, err
		}
		switch t := tok.(type) {
		case xml.StartElement:
			depth++
			enc.EncodeToken(t)
		case xml.EndElement:
			depth--
			enc.EncodeToken(t)
		case xml.CharData:
			enc.EncodeToken(t)
		case xml.Comment:
			enc.EncodeToken(t)
		}
	}

	enc.Flush()
	return []byte(buf.String()), nil
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

func main() {
	var (
		inputPath  string
		outputPath string
		extraURLs  multiFlag
	)

	flag.Var(&extraURLs, "extra-url", "Remote XMLTV URL to include (repeatable)")
	flag.StringVar(&inputPath, "input", "", "Directory containing XMLTV files to merge (required)")
	flag.StringVar(&outputPath, "output", "", "Output path for merged epg.xml (required)")
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

	for _, u := range extraURLs {
		path, err := downloadToTemp(u)
		if err != nil {
			log.Printf("Warning: failed to download %s: %v", u, err)
			continue
		}
		defer os.Remove(path)
		srcFiles = append(srcFiles, path)
		log.Printf("  (remote) %s", u)
	}

	log.Printf("Found %d XML file(s) in %s", len(srcFiles), inputPath)
	for _, f := range srcFiles {
		log.Printf("  %s", f)
	}

	start := time.Now()
	log.Printf("Merging %d source file(s)...", len(srcFiles))

	if err := mergeEPGFiles(srcFiles, outputPath); err != nil {
		log.Fatalf("Merge failed: %v", err)
	}

	log.Printf("Done in %.1fs — EPG written to %s", time.Since(start).Seconds(), outputPath)
}

// downloadToTemp fetches a remote XMLTV URL to a temp file and returns the path.
func downloadToTemp(url string) (string, error) {
	resp, err := http.Get(url)
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return "", fmt.Errorf("HTTP %d for %s", resp.StatusCode, url)
	}

	tmp, err := os.CreateTemp("", "epg-extra-*.xml")
	if err != nil {
		return "", err
	}

	if _, err := io.Copy(tmp, resp.Body); err != nil {
		tmp.Close()
		os.Remove(tmp.Name())
		return "", err
	}

	tmp.Close()
	return tmp.Name(), nil
}
