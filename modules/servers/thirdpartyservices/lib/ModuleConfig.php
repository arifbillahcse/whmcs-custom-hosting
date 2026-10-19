<?php

declare(strict_types=1);

namespace WHMCS\Module\Server\ThirdPartyServices;

/**
 * Product-level settings (Products/Services -> Module Settings).
 *
 * WHMCS stores these by position as configoption1..N, so the order of
 * definitions() must never change once products use the module. Add new
 * settings at the end only.
 */
final class ModuleConfig
{
    public const DEFAULT_REMINDER_DAYS = 7;

    public function __construct(
        public readonly string $defaultProvider,
        public readonly PanelType $defaultPanelType,
        public readonly bool $hidePassword,
        public readonly bool $allowAutoLogin,
        public readonly int $reminderDays,
    ) {
    }

    /**
     * Field definitions returned by thirdpartyservices_ConfigOptions().
     */
    public static function definitions(): array
    {
        return [
            // configoption1
            'Default Provider' => [
                'Type' => 'text',
                'Size' => '30',
                'Default' => 'Namecheap',
                'Description' => 'Upstream provider pre-filled on new services (admin only)',
            ],
            // configoption2
            'Default Panel Type' => [
                'Type' => 'dropdown',
                'Options' => PanelType::options(),
                'Default' => PanelType::CPANEL->value,
                'Description' => 'Pre-selected panel type on new services',
            ],
            // configoption3
            'Hide Password' => [
                'Type' => 'yesno',
                'Description' => 'Tick to hide the panel password from the client area',
            ],
            // configoption4
            'Allow Auto-Login' => [
                'Type' => 'yesno',
                'Description' => 'Tick to allow one-click login for panels that support it (cPanel, DirectAdmin)',
            ],
            // configoption5
            'Renewal Reminder Days' => [
                'Type' => 'text',
                'Size' => '5',
                'Default' => (string) self::DEFAULT_REMINDER_DAYS,
                'Description' => 'Create an admin To-Do this many days before the upstream renewal date',
            ],
        ];
    }

    public static function fromParams(array $params): self
    {
        $days = filter_var(
            $params['configoption5'] ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 365]]
        );

        return new self(
            defaultProvider: trim((string) ($params['configoption1'] ?? '')),
            defaultPanelType: PanelType::fromInput($params['configoption2'] ?? null),
            hidePassword: self::isTicked($params['configoption3'] ?? ''),
            allowAutoLogin: self::isTicked($params['configoption4'] ?? ''),
            reminderDays: $days === false ? self::DEFAULT_REMINDER_DAYS : $days,
        );
    }

    private static function isTicked(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['on', 'yes', '1', 'true'], true);
    }
}
