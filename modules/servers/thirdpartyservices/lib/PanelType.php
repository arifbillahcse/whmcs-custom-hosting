<?php

declare(strict_types=1);

namespace WHMCS\Module\Server\ThirdPartyServices;

/**
 * Control panels the upstream hosting can use.
 */
enum PanelType: string
{
    case CPANEL = 'cpanel';
    case CYBERPANEL = 'cyberpanel';
    case DIRECTADMIN = 'directadmin';
    case PLESK = 'plesk';
    case AAPANEL = 'aapanel';
    case CLOUDPANEL = 'cloudpanel';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CPANEL => 'cPanel',
            self::CYBERPANEL => 'CyberPanel',
            self::DIRECTADMIN => 'DirectAdmin',
            self::PLESK => 'Plesk',
            self::AAPANEL => 'aaPanel',
            self::CLOUDPANEL => 'CloudPanel',
            self::OTHER => 'Other / Custom',
        };
    }

    /**
     * Example login URL shown as a hint in the admin form.
     */
    public function urlPlaceholder(): string
    {
        return match ($this) {
            self::CPANEL => 'https://server.example.com:2083',
            self::CYBERPANEL => 'https://203.0.113.10:8090',
            self::DIRECTADMIN => 'https://server.example.com:2222',
            self::PLESK, self::CLOUDPANEL => 'https://server.example.com:8443',
            self::AAPANEL => 'https://203.0.113.10:7800/your-entry-path',
            self::OTHER => 'https://panel.example.com',
        };
    }

    /**
     * Whether the panel's login page accepts a plain username/password form
     * post, so the client area can offer one-click login (used in Phase 2).
     * Panels with their own CSRF/2FA login flow only get "open login page".
     */
    public function supportsAutoLogin(): bool
    {
        return match ($this) {
            self::CPANEL, self::DIRECTADMIN => true,
            default => false,
        };
    }

    /**
     * Accepts either the stored value ("cpanel") or the display label
     * ("cPanel"); anything unknown falls back to OTHER.
     */
    public static function fromInput(?string $input): self
    {
        $input = trim((string) $input);

        $type = self::tryFrom(strtolower($input));
        if ($type !== null) {
            return $type;
        }

        foreach (self::cases() as $case) {
            if (strcasecmp($case->label(), $input) === 0) {
                return $case;
            }
        }

        return self::OTHER;
    }

    /**
     * @return array<string, string> value => label, for dropdowns.
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
