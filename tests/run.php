<?php

// Offline test for the module, run with: composer install && composer test

declare(strict_types=1);

// Minimal WHMCS stand-ins for testing the module outside WHMCS.
namespace {
    require dirname(__DIR__) . '/vendor/autoload.php';
    require __DIR__ . '/capsule_stub.php';

    define('WHMCS', true);
    $capsule = new \WHMCS\Database\Capsule();
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
    $capsule->setAsGlobal();

    $GLOBALS['moduleLog'] = [];
    function logModuleCall($module, $action, $request, $response, $processed = '', $replace = [])
    {
        $GLOBALS['moduleLog'][] = compact('module', 'action', 'request', 'response', 'replace');
    }
    $_SESSION = [];

    require dirname(__DIR__) . '/modules/servers/thirdpartyservices/thirdpartyservices.php';

    $pass = 0;
    $fail = 0;
    function check(string $name, bool $ok): void
    {
        global $pass, $fail;
        $ok ? $pass++ : $fail++;
        echo ($ok ? "PASS " : "FAIL ") . $name . "\n";
    }

    use WHMCS\Module\Server\ThirdPartyServices\AccountRepository;
    use WHMCS\Module\Server\ThirdPartyServices\Input;
    use WHMCS\Module\Server\ThirdPartyServices\ModuleConfig;
    use WHMCS\Module\Server\ThirdPartyServices\PanelType;

    // MetaData / ConfigOptions
    $meta = thirdpartyservices_MetaData();
    check('MetaData display name', $meta['DisplayName'] === 'Third-Party Services' && $meta['RequiresServer'] === false);
    $opts = thirdpartyservices_ConfigOptions();
    check('5 config options in order', array_keys($opts) === ['Default Provider', 'Default Panel Type', 'Hide Password', 'Allow Auto-Login', 'Renewal Reminder Days']);
    check('panel dropdown has 7 options', count($opts['Default Panel Type']['Options']) === 7);

    // ModuleConfig parsing
    $cfg = ModuleConfig::fromParams(['configoption1' => ' Namecheap ', 'configoption2' => 'CyberPanel', 'configoption3' => 'on', 'configoption4' => '', 'configoption5' => 'abc']);
    check('config provider trimmed', $cfg->defaultProvider === 'Namecheap');
    check('panel type from label', $cfg->defaultPanelType === PanelType::CYBERPANEL);
    check('yesno on/off', $cfg->hidePassword === true && $cfg->allowAutoLogin === false);
    check('invalid reminder days -> default 7', $cfg->reminderDays === 7);
    check('unknown panel -> other', PanelType::fromInput('weird') === PanelType::OTHER);
    check('auto-login only cpanel/directadmin', PanelType::CPANEL->supportsAutoLogin() && !PanelType::PLESK->supportsAutoLogin());

    // Input validation
    check('url ok + trailing slash trimmed', Input::url('https://srv.example.com:2083/') === 'https://srv.example.com:2083');
    check('url javascript rejected', Input::url('javascript:alert(1)') === null);
    check('url ftp rejected', Input::url('ftp://x.example.com') === null);
    check('url empty allowed', Input::url('  ') === '');
    check('date valid', Input::date('2026-11-01') === '2026-11-01');
    check('date invalid', Input::date('2026-02-30') === null);
    check('ip v4/v6', Input::ip('203.0.113.10') === '203.0.113.10' && Input::ip('2001:db8::1') === '2001:db8::1');
    check('ip invalid', Input::ip('999.1.1.1') === null);
    check('nameservers ok', Input::nameservers("DNS1.Example.com\r\n\r\ndns2.example.com\n") === ['dns1.example.com', 'dns2.example.com']);
    check('nameservers bad', Input::nameservers("dns1.example.com\nnot a host") === null);
    check('text strips control chars', Input::text("Name\x00cheap\n", 100) === 'Namecheap');

    $params = ['serviceid' => 42, 'domain' => 'example.com', 'username' => 'client42', 'password' => 'S3cr3t!',
        'configoption1' => 'Namecheap', 'configoption2' => 'cpanel', 'configoption3' => '', 'configoption4' => 'on', 'configoption5' => '7'];

    // Admin tab before any record exists (table auto-created)
    $fields = thirdpartyservices_AdminServicesTabFields($params);
    check('table created on first use', \WHMCS\Database\Capsule::schema()->hasTable(AccountRepository::TABLE));
    check('admin fields rendered', isset($fields['Control Panel URL'], $fields['Panel Type'], $fields['Admin Notes']));
    check('provider default prefilled', str_contains($fields['Provider'], 'value="Namecheap"'));

    // CreateAccount initialises record
    check('CreateAccount success', thirdpartyservices_CreateAccount($params) === 'success');
    $acc = AccountRepository::find(42, ModuleConfig::fromParams($params));
    check('record exists with defaults', $acc->exists && $acc->provider === 'Namecheap' && $acc->panelType === PanelType::CPANEL);
    check('CreateAccount idempotent', thirdpartyservices_CreateAccount($params) === 'success'
        && \WHMCS\Database\Capsule::table(AccountRepository::TABLE)->count() === 1);
    $last = end($GLOBALS['moduleLog']);
    check('password masked in log', $last['replace'] === ['S3cr3t!']
        && !str_contains(json_encode([$last['request'], $last['response']]), 'S3cr3t!'));

    // Save valid data
    $_POST = ['tps_form' => '1', 'tps_provider' => 'Hostinger', 'tps_panel_type' => 'cyberpanel',
        'tps_panel_url' => 'https://203.0.113.10:8090/', 'tps_webmail_url' => '', 'tps_server_ip' => '203.0.113.10',
        'tps_nameservers' => "ns1.host.com\nns2.host.com", 'tps_client_note' => "Line1\r\nLine2",
        'tps_extra_details' => 'FTP: ftp.example.com', 'tps_admin_notes' => 'Order #999 cost $3',
        'tps_upstream_due_date' => '2026-12-01'];
    thirdpartyservices_AdminServicesTabFieldsSave($params);
    $acc = AccountRepository::find(42, ModuleConfig::fromParams($params));
    check('valid save stored', $acc->provider === 'Hostinger' && $acc->panelType === PanelType::CYBERPANEL
        && $acc->panelUrl === 'https://203.0.113.10:8090' && $acc->serverIp === '203.0.113.10'
        && $acc->nameservers === ['ns1.host.com', 'ns2.host.com'] && $acc->clientNote === "Line1\nLine2"
        && $acc->upstreamDueDate === '2026-12-01');
    check('no errors queued', empty($_SESSION['thirdpartyservices_errors'][42]));

    // Invalid data keeps previous values and reports errors
    $_POST['tps_panel_url'] = 'javascript:alert(1)';
    $_POST['tps_server_ip'] = 'bad-ip';
    $_POST['tps_admin_notes'] = 'Updated note';
    thirdpartyservices_AdminServicesTabFieldsSave($params);
    $acc = AccountRepository::find(42, ModuleConfig::fromParams($params));
    check('invalid url kept old value', $acc->panelUrl === 'https://203.0.113.10:8090');
    check('invalid ip kept old value', $acc->serverIp === '203.0.113.10');
    check('valid fields still saved', $acc->adminNotes === 'Updated note');
    $fields = thirdpartyservices_AdminServicesTabFields($params);
    check('errors shown once', str_contains($fields['Third-Party Status'], 'Control Panel URL must be')
        && str_contains($fields['Third-Party Status'], 'Server IP must be'));
    $fields = thirdpartyservices_AdminServicesTabFields($params);
    check('errors cleared after display', !str_contains($fields['Third-Party Status'], 'alert-danger'));

    // XSS escaping in admin fields
    $_POST = ['tps_form' => '1', 'tps_provider' => '"><script>x</script>', 'tps_panel_type' => 'cpanel',
        'tps_upstream_due_date' => ''];
    thirdpartyservices_AdminServicesTabFieldsSave($params);
    $fields = thirdpartyservices_AdminServicesTabFields($params);
    check('provider escaped', !str_contains($fields['Provider'], '<script>') && str_contains($fields['Provider'], '&lt;script&gt;'));
    $acc = AccountRepository::find(42, ModuleConfig::fromParams($params));
    check('empty date clears renewal date', $acc->upstreamDueDate === null);

    // Save without marker does nothing
    $_POST = ['tps_provider' => 'Should not save'];
    thirdpartyservices_AdminServicesTabFieldsSave($params);
    $acc = AccountRepository::find(42, ModuleConfig::fromParams($params));
    check('no marker -> no save', $acc->provider !== 'Should not save');

    check('lifecycle stubs succeed', thirdpartyservices_SuspendAccount($params) === 'success'
        && thirdpartyservices_UnsuspendAccount($params) === 'success'
        && thirdpartyservices_TerminateAccount($params) === 'success');

    echo "\n$pass passed, $fail failed\n";
    exit($fail ? 1 : 0);
}
