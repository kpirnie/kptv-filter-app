<?php

/**
 * user/login.php
 * 
 * No direct access allowed!
 * 
 * @since 8.3
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// define the primary app path if not already defined
defined('KPTV_PATH') || die('Direct Access is not allowed!');

// pull in the header
KPTV::pull_header();

// check if we're already logged in
if (! KPTV_User::is_user_logged_in()) {

    // message with redirect
    KPTV::message_with_redirect('/', 'danger', 'You don\'t belong there.');
} else {

?>
    <h2 class="kptv-heading uk-heading-bullet">Change Your Password</h2>
    <form action="/users/changepass" method="POST" class="uk-form-stacked" style="padding-bottom: 40px !important;">
        <?php echo \KPT\Token::field('csrf'); ?>
        <div class="uk-margin">
            <div class="uk-inline uk-width-1-1">
                <span class="uk-form-icon" uk-icon="unlock"></span>
                <input class="uk-input" id="frmExistPassword" type="password" placeholder="Your Current Password" name="frmExistPassword" />
            </div>
        </div>
        <div class="uk-margin">
            <div class="uk-inline uk-width-1-1">
                <span class="uk-form-icon" uk-icon="lock"></span>
                <input class="uk-input" id="frmNewPassword1" type="password" placeholder="New Password" name="frmNewPassword1" />
            </div>
        </div>
        <div class="uk-margin">
            <div class="uk-inline uk-width-1-1">
                <span class="uk-form-icon" uk-icon="lock"></span>
                <input class="uk-input" id="frmNewPassword2" type="password" placeholder="New Password Again" name="frmNewPassword2" />
            </div>
        </div>
        <div class="uk-margin">
            <div class="uk-width-1-1">
                <button class="uk-button uk-button-primary uk-border-rounded contact-button uk-align-right" type="submit">
                    Change Your Password <span uk-icon="cog"></span>
                </button>
            </div>
        </div>
    </form>
    <h2 class="kptv-heading uk-heading-bullet uk-margin-large-top">Your Export Token</h2>
    <p class="uk-text-meta">This token is used in your playlist URLs and IPTV app connections. Regenerating it will break all existing connections until you update them.</p>
    <div class="uk-flex uk-flex-middle" style="gap: 12px;">
        <code class="uk-text-break"><?php echo htmlspecialchars(KPTV::getExportToken(KPTV_User::get_current_user()->id)); ?></code>
    </div>
    <form action="/users/export-token/regenerate" method="POST" class="uk-margin-top">
        <?php echo \KPT\Token::field('csrf'); ?>
        <button class="uk-button uk-button-danger uk-border-rounded" type="submit"
            onclick="return confirm('Are you sure? This will break all existing playlist URLs and IPTV app connections.')">
            Regenerate Export Token <span uk-icon="refresh"></span>
        </button>
    </form>
<?php

}

// pull in the footer
KPTV::pull_footer();
