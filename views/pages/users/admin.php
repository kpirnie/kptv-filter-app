<?php

/**
 * User Admin
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined('KPTV_PATH') || die('Direct Access is not allowed!');

// setup the user
$user = \KPTV_User::get_current_user();
$userId = $user->id;

// Configure database via constructor
$dbconf = (array) \KPTV::get_setting('database');

// fire up the datatables class
$dt = new \KPT\DataTables($dbconf);

// setup the forms
$formFieldsConfig = \KPTV::view_configs('users')->form ?? [];

// remove the attributes for the add form's username field
$addForm = $formFieldsConfig;
unset($addForm['u_name']['attributes']);

// we need to add a couple fields to the addForm
$pos = array_search('u_name', array_keys($addForm)) + 1;
$addForm = array_slice($addForm, 0, $pos, true)
    + ['u_pass' => [
        'type' => 'password',
        'required' => true,
        'class' => 'uk-width-1-1 uk-margin-bottom',
        'label' => 'Password',
        'tab' => 'general',
    ]]
    + array_slice($addForm, $pos, null, true);

// make the u_active, u_role, locked_until fields read-only/disabled if we're editting our own record
$editingId = isset($_GET['action']) && $_GET['action'] === 'fetch_record'
    ? (int)($_GET['id'] ?? 0)
    : 0;
$isSelf = $editingId > 0 && $editingId === (int)$userId;
$editForm = $formFieldsConfig;
if ($isSelf) {
    foreach (['u_active', 'u_role', 'locked_until'] as $field) {
        $editForm[$field]['attributes'] = ['disabled' => 'true'];
    }
}
// this is so the current user cannot edit their own role & deactviate themselves
foreach (['u_role', 'u_active', 'locked_until'] as $field) {
    $editForm[$field]['allow_on'] = [
        'field'    => 'id',
        'value'    => (int)$userId,
        'operator' => '==',
        'action'   => [
            'set_attributes' => ['disabled' => 'true'],
        ],
    ];
}

// configure the datatable
$dt->table('kptv_users')
    ->primaryKey('id')  // Use qualified primary key
    ->tableClass('uk-table uk-table-divider uk-table-small uk-margin-bottom')
    ->columns([
        'id' => 'ID',
        'u_role' => ['label' => 'Role', 'type' => 'select', 'options' => ['0' => 'User', '99' => 'Admin']],
        'u_active' => ['type' => 'boolean', 'label' => 'Active?'],
        'u_name' => 'Username',
        'u_email' => 'Email',
        'last_login' => 'Last Login',
    ])
    ->columnClasses([
        'id' => 'hide-col',
    ])
    ->sortable(['u_role', 'u_active', 'u_name', 'u_email'])
    ->inlineEditable(['u_role', 'u_email',])
    ->defaultSort('u_role', 'ASC')
    ->perPage(25)
    ->pageSizeOptions([25, 50, 100, 250], true)
    ->addForm('Add a User', $addForm, class: 'uk-grid-small uk-grid')
    ->editForm('Edit a User', $editForm, class: 'uk-grid-small uk-grid');

// Handle AJAX requests (before any HTML output)
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dt->handleAjax();
}

// pull in the header
\KPTV::pull_header();
?>
<h2 class="kptv-heading uk-heading-bullet">User Admin</h2>
<div class="uk-border-bottom">
    <?php

    // pull in the control panel
    \KPTV::include_view('common/control-panel', ['dt' => $dt, 'position' => 'top']);
    ?>
</div>
<div class="uk-margin">
    <?php

    // write out the datatable component
    echo $dt->renderDataTableComponent();
    ?>
</div>
<div class="uk-border-top">
    <?php

    // pull in the control panel
    \KPTV::include_view('common/control-panel', ['dt' => $dt, 'position' => 'bottom']);
    ?>
</div>
<?php

// pull in the footer
\KPTV::pull_footer();
