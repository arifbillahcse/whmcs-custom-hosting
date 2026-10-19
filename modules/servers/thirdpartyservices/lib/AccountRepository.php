<?php

declare(strict_types=1);

namespace WHMCS\Module\Server\ThirdPartyServices;

use WHMCS\Database\Capsule;

final class AccountRepository
{
    public const TABLE = 'mod_thirdpartyservices_accounts';

    /**
     * Provisioning modules have no activate() hook, so the table is created
     * on first use. Later phases add columns here with hasColumn() checks.
     */
    public static function ensureSchema(): void
    {
        $schema = Capsule::schema();
        if ($schema->hasTable(self::TABLE)) {
            return;
        }

        $schema->create(self::TABLE, function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('service_id')->unique();
            $table->string('provider', 100)->default('');
            $table->string('panel_type', 32)->default(PanelType::OTHER->value);
            $table->string('panel_url', 255)->default('');
            $table->string('webmail_url', 255)->default('');
            $table->string('server_ip', 64)->default('');
            $table->text('nameservers')->nullable();
            $table->text('client_note')->nullable();
            $table->text('extra_details')->nullable();
            $table->text('admin_notes')->nullable();
            $table->date('upstream_due_date')->nullable();
            $table->date('reminder_sent_for')->nullable();
            $table->boolean('is_ready')->default(false);
            $table->dateTime('ready_at')->nullable();
            $table->timestamps();
        });
    }

    public static function find(int $serviceId, ModuleConfig $config): ServiceAccount
    {
        self::ensureSchema();

        $row = Capsule::table(self::TABLE)->where('service_id', $serviceId)->first();

        return $row ? ServiceAccount::fromRow($row) : ServiceAccount::blank($serviceId, $config);
    }

    /**
     * Insert or update the record for a service.
     *
     * @param array<string, mixed> $data column => value
     */
    public static function save(int $serviceId, array $data): void
    {
        self::ensureSchema();

        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        $query = Capsule::table(self::TABLE)->where('service_id', $serviceId);

        if ($query->exists()) {
            $query->update($data);
            return;
        }

        $data['service_id'] = $serviceId;
        $data['created_at'] = $now;
        Capsule::table(self::TABLE)->insert($data);
    }

    /**
     * Create the record with product defaults if the service has none yet.
     */
    public static function initialise(int $serviceId, ModuleConfig $config): void
    {
        if (self::find($serviceId, $config)->exists) {
            return;
        }

        self::save($serviceId, [
            'provider' => $config->defaultProvider,
            'panel_type' => $config->defaultPanelType->value,
        ]);
    }
}
