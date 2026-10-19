<?php

declare(strict_types=1);

/**
 * Third-Party Services - WHMCS provisioning module
 *
 * For hosting (or any panel-based service) bought from another provider and
 * resold through WHMCS. The admin enters the panel URL and login details once,
 * and the client can see them in the client area.
 *
 * Requirements: WHMCS 9.0.x, PHP 8.2+
 *
 * Phase 1: module registration, product settings, database table and admin
 * service fields. Client area output, notifications and lifecycle To-Dos are
 * added in later phases.
 */

use WHMCS\Module\Server\ThirdPartyServices\AccountRepository;
use WHMCS\Module\Server\ThirdPartyServices\AdminServiceFields;
use WHMCS\Module\Server\ThirdPartyServices\ModuleConfig;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'WHMCS\\Module\\Server\\ThirdPartyServices\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/lib/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

function thirdpartyservices_MetaData(): array
{
    return [
        'DisplayName' => 'Third-Party Services',
        'APIVersion' => '1.1',
        'RequiresServer' => false,
    ];
}

function thirdpartyservices_ConfigOptions(): array
{
    return ModuleConfig::definitions();
}

/**
 * Runs when the service is set up (e.g. after first payment). Phase 1 only
 * creates the details record with the product defaults; the "buy at
 * provider" To-Do is added in Phase 3.
 */
function thirdpartyservices_CreateAccount(array $params): string
{
    try {
        AccountRepository::initialise((int) $params['serviceid'], ModuleConfig::fromParams($params));
        thirdpartyservices_log(__FUNCTION__, $params, 'Details record initialised');

        return 'success';
    } catch (\Throwable $e) {
        thirdpartyservices_log(__FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }
}

/**
 * Suspend / Unsuspend / Terminate only change the status inside WHMCS for
 * now. Phase 4 adds To-Dos for the matching action at the provider.
 */
function thirdpartyservices_SuspendAccount(array $params): string
{
    thirdpartyservices_log(__FUNCTION__, $params, 'Status changed in WHMCS only');

    return 'success';
}

function thirdpartyservices_UnsuspendAccount(array $params): string
{
    thirdpartyservices_log(__FUNCTION__, $params, 'Status changed in WHMCS only');

    return 'success';
}

function thirdpartyservices_TerminateAccount(array $params): string
{
    thirdpartyservices_log(__FUNCTION__, $params, 'Status changed in WHMCS only');

    return 'success';
}

function thirdpartyservices_AdminServicesTabFields(array $params): array
{
    try {
        $account = AccountRepository::find((int) $params['serviceid'], ModuleConfig::fromParams($params));

        return AdminServiceFields::render($account);
    } catch (\Throwable $e) {
        thirdpartyservices_log(__FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return [
            'Third-Party Services' => '<div class="alert alert-danger">Could not load details: '
                . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>',
        ];
    }
}

function thirdpartyservices_AdminServicesTabFieldsSave(array $params): void
{
    try {
        $current = AccountRepository::find((int) $params['serviceid'], ModuleConfig::fromParams($params));
        AdminServiceFields::save($current, $_POST);
    } catch (\Throwable $e) {
        thirdpartyservices_log(__FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
    }
}

/**
 * Module log entry (Utilities -> Logs -> Module Log). The service password
 * is masked so it never appears in the log.
 */
function thirdpartyservices_log(string $action, array $params, mixed $response, string $processed = ''): void
{
    $request = [
        'serviceid' => $params['serviceid'] ?? null,
        'domain' => $params['domain'] ?? null,
        'username' => $params['username'] ?? null,
    ];
    $mask = array_values(array_filter([(string) ($params['password'] ?? '')], 'strlen'));

    logModuleCall('thirdpartyservices', $action, $request, $response, $processed, $mask);
}
