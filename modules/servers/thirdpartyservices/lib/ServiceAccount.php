<?php

declare(strict_types=1);

namespace WHMCS\Module\Server\ThirdPartyServices;

/**
 * Upstream hosting details for one WHMCS service.
 *
 * The panel username and password are not part of this object: they live in
 * the standard WHMCS service Username/Password fields, where WHMCS encrypts
 * the password.
 */
final class ServiceAccount
{
    /**
     * @param string[] $nameservers
     */
    public function __construct(
        public readonly int $serviceId,
        public readonly string $provider,
        public readonly PanelType $panelType,
        public readonly string $panelUrl,
        public readonly string $webmailUrl,
        public readonly string $serverIp,
        public readonly array $nameservers,
        public readonly string $clientNote,
        public readonly string $extraDetails,
        public readonly string $adminNotes,
        public readonly ?string $upstreamDueDate,
        public readonly ?string $reminderSentFor,
        public readonly bool $isReady,
        public readonly ?string $readyAt,
        public readonly bool $exists,
    ) {
    }

    /**
     * Unsaved record pre-filled from the product's module settings.
     */
    public static function blank(int $serviceId, ModuleConfig $config): self
    {
        return new self(
            serviceId: $serviceId,
            provider: $config->defaultProvider,
            panelType: $config->defaultPanelType,
            panelUrl: '',
            webmailUrl: '',
            serverIp: '',
            nameservers: [],
            clientNote: '',
            extraDetails: '',
            adminNotes: '',
            upstreamDueDate: null,
            reminderSentFor: null,
            isReady: false,
            readyAt: null,
            exists: false,
        );
    }

    public static function fromRow(object $row): self
    {
        return new self(
            serviceId: (int) $row->service_id,
            provider: (string) $row->provider,
            panelType: PanelType::fromInput($row->panel_type),
            panelUrl: (string) $row->panel_url,
            webmailUrl: (string) $row->webmail_url,
            serverIp: (string) $row->server_ip,
            nameservers: Input::lines((string) $row->nameservers),
            clientNote: (string) $row->client_note,
            extraDetails: (string) $row->extra_details,
            adminNotes: (string) $row->admin_notes,
            upstreamDueDate: self::dateOrNull($row->upstream_due_date),
            reminderSentFor: self::dateOrNull($row->reminder_sent_for),
            isReady: (bool) $row->is_ready,
            readyAt: $row->ready_at !== null ? (string) $row->ready_at : null,
            exists: true,
        );
    }

    private static function dateOrNull(mixed $value): ?string
    {
        // Some MySQL modes return 0000-00-00 for empty dates.
        if ($value === null || $value === '' || str_starts_with((string) $value, '0000')) {
            return null;
        }

        return substr((string) $value, 0, 10);
    }
}
