<?php

declare(strict_types=1);

namespace WHMCS\Module\Server\ThirdPartyServices;

/**
 * Extra fields on Admin -> Clients -> Products/Services for this module.
 *
 * Field names are prefixed with "tps_" so they cannot collide with WHMCS's
 * own service form fields.
 */
final class AdminServiceFields
{
    /** Hidden marker: only save when our fields were really on the form. */
    private const FORM_MARKER = 'tps_form';

    /** Validation errors are kept in the session until the next page load. */
    private const SESSION_ERRORS = 'thirdpartyservices_errors';

    /**
     * @return array<string, string> label => HTML
     */
    public static function render(ServiceAccount $account): array
    {
        $e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $panelOptions = '';
        foreach (PanelType::cases() as $type) {
            $panelOptions .= sprintf(
                '<option value="%s" data-placeholder="%s"%s>%s</option>',
                $e($type->value),
                $e($type->urlPlaceholder()),
                $type === $account->panelType ? ' selected' : '',
                $e($type->label())
            );
        }

        $status = $account->isReady
            ? '<span class="label label-success">Ready - visible to client</span>'
                . ($account->readyAt ? ' <small class="text-muted">since ' . $e($account->readyAt) . '</small>' : '')
            : '<span class="label label-warning">Not ready</span>';

        $errors = self::pullErrors($account->serviceId);
        if ($errors) {
            $status .= '<div class="alert alert-danger" style="margin:8px 0 0;">'
                . '<strong>Not saved (previous values kept):</strong><br>'
                . implode('<br>', array_map($e, $errors))
                . '</div>';
        }

        return [
            'Third-Party Status' => $status
                . '<input type="hidden" name="' . self::FORM_MARKER . '" value="1">'
                . '<br><small class="text-muted">Panel login username and password use the standard '
                . 'Username / Password fields of this service.</small>',
            'Provider' => '<input type="text" name="tps_provider" class="form-control input-300" maxlength="100" value="'
                . $e($account->provider) . '" placeholder="e.g. Namecheap">',
            'Panel Type' => '<select name="tps_panel_type" id="tpsPanelType" class="form-control select-inline">'
                . $panelOptions . '</select>',
            'Control Panel URL' => '<input type="url" name="tps_panel_url" id="tpsPanelUrl" class="form-control input-400" maxlength="255" value="'
                . $e($account->panelUrl) . '" placeholder="' . $e($account->panelType->urlPlaceholder()) . '">',
            'Webmail URL' => '<input type="url" name="tps_webmail_url" class="form-control input-400" maxlength="255" value="'
                . $e($account->webmailUrl) . '" placeholder="Optional">',
            'Server IP' => '<input type="text" name="tps_server_ip" class="form-control input-200" maxlength="64" value="'
                . $e($account->serverIp) . '" placeholder="Optional">',
            'Nameservers' => '<textarea name="tps_nameservers" class="form-control input-400" rows="3" placeholder="One per line (optional)">'
                . $e(implode("\n", $account->nameservers)) . '</textarea>',
            'Note For Client' => '<textarea name="tps_client_note" class="form-control input-400" rows="3" '
                . 'placeholder="Shown to the client, e.g. setup tips">' . $e($account->clientNote) . '</textarea>',
            'Extra Login Details' => '<textarea name="tps_extra_details" class="form-control input-400" rows="3" '
                . 'placeholder="Shown to the client, e.g. FTP host, SSH port, database host">' . $e($account->extraDetails) . '</textarea>',
            'Upstream Renewal Date' => '<input type="date" name="tps_upstream_due_date" class="form-control input-200" value="'
                . $e($account->upstreamDueDate) . '"> <small class="text-muted">When you must renew at the provider</small>',
            'Admin Notes' => '<textarea name="tps_admin_notes" class="form-control input-400" rows="3" '
                . 'placeholder="Private: provider order ID, provider login email, cost...">' . $e($account->adminNotes) . '</textarea>'
                . self::placeholderScript(),
        ];
    }

    /**
     * Validate the posted fields and save them. Invalid fields keep their
     * previous value and are reported on the next page load.
     */
    public static function save(ServiceAccount $current, array $request): void
    {
        if (empty($request[self::FORM_MARKER])) {
            return;
        }

        $get = static fn (string $key): string => is_string($request[$key] ?? null) ? $request[$key] : '';
        $errors = [];
        $data = [
            'provider' => Input::text($get('tps_provider'), 100),
            'panel_type' => PanelType::fromInput($get('tps_panel_type'))->value,
            'client_note' => Input::multiline($get('tps_client_note')),
            'extra_details' => Input::multiline($get('tps_extra_details')),
            'admin_notes' => Input::multiline($get('tps_admin_notes')),
        ];

        $panelUrl = Input::url($get('tps_panel_url'));
        if ($panelUrl === null) {
            $errors[] = 'Control Panel URL must be a full http(s) URL, e.g. ' . PanelType::fromInput($get('tps_panel_type'))->urlPlaceholder();
        } else {
            $data['panel_url'] = $panelUrl;
        }

        $webmailUrl = Input::url($get('tps_webmail_url'));
        if ($webmailUrl === null) {
            $errors[] = 'Webmail URL must be a full http(s) URL.';
        } else {
            $data['webmail_url'] = $webmailUrl;
        }

        $serverIp = Input::ip($get('tps_server_ip'));
        if ($serverIp === null) {
            $errors[] = 'Server IP must be a valid IPv4 or IPv6 address.';
        } else {
            $data['server_ip'] = $serverIp;
        }

        $nameservers = Input::nameservers($get('tps_nameservers'));
        if ($nameservers === null) {
            $errors[] = 'Nameservers must be hostnames (e.g. dns1.example.com), one per line, maximum 10.';
        } else {
            $data['nameservers'] = implode("\n", $nameservers);
        }

        $dueDate = Input::date($get('tps_upstream_due_date'));
        if ($dueDate === null) {
            $errors[] = 'Upstream Renewal Date must be a valid date (YYYY-MM-DD).';
        } else {
            $data['upstream_due_date'] = $dueDate === '' ? null : $dueDate;
            // A new renewal date needs a new reminder (Phase 4).
            if ($data['upstream_due_date'] !== $current->upstreamDueDate) {
                $data['reminder_sent_for'] = null;
            }
        }

        AccountRepository::save($current->serviceId, $data);

        if ($errors) {
            $_SESSION[self::SESSION_ERRORS][$current->serviceId] = $errors;
        }
    }

    /**
     * @return string[]
     */
    private static function pullErrors(int $serviceId): array
    {
        $errors = $_SESSION[self::SESSION_ERRORS][$serviceId] ?? [];
        unset($_SESSION[self::SESSION_ERRORS][$serviceId]);

        return is_array($errors) ? $errors : [];
    }

    /**
     * Updates the URL placeholder when the panel type changes.
     */
    private static function placeholderScript(): string
    {
        return <<<'HTML'
<script>
(function () {
    var select = document.getElementById('tpsPanelType');
    var url = document.getElementById('tpsPanelUrl');
    if (!select || !url) { return; }
    select.addEventListener('change', function () {
        var option = select.options[select.selectedIndex];
        url.placeholder = option.getAttribute('data-placeholder') || '';
    });
})();
</script>
HTML;
    }
}
