<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add roles for list and show operations on locale and gift list related resources
 * for BASIC and VERIFIED user groups
 */
final class Version_20260109_02 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add list and show roles for locale and gift list resources (BASIC, VERIFIED groups)';
    }

    public function up(Schema $schema): void
    {
        // Country - list and show
        $this->addSql("SELECT app.add_role('ROLE_COUNTRY_LIST', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_COUNTRY_SHOW', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");

        // CountryCurrency - list and show
        $this->addSql("SELECT app.add_role('ROLE_COUNTRY_CURRENCY_LIST', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_COUNTRY_CURRENCY_SHOW', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");

        // CountryLanguageHint - list and show
        $this->addSql("SELECT app.add_role('ROLE_COUNTRY_LANGUAGE_HINT_LIST', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_COUNTRY_LANGUAGE_HINT_SHOW', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");

        // Currency - list and show
        $this->addSql("SELECT app.add_role('ROLE_CURRENCY_LIST', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_CURRENCY_SHOW', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");

        // GiftListItemComment - list and show
        $this->addSql("SELECT app.add_role('ROLE_GIFT_LIST_ITEM_COMMENT_LIST', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_GIFT_LIST_ITEM_COMMENT_SHOW', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");

        // GiftListItemLink - list and show
        $this->addSql("SELECT app.add_role('ROLE_GIFT_LIST_ITEM_LINK_LIST', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_GIFT_LIST_ITEM_LINK_SHOW', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");

        // GiftListItemReservation - list and show
        $this->addSql("SELECT app.add_role('ROLE_GIFT_LIST_ITEM_RESERVATION_LIST', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_GIFT_LIST_ITEM_RESERVATION_SHOW', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");

        // GiftListItemReservationStatus - list and show
        $this->addSql("SELECT app.add_role('ROLE_GIFT_LIST_ITEM_RESERVATION_STATUS_LIST', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_GIFT_LIST_ITEM_RESERVATION_STATUS_SHOW', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");

        // GiftListItemStatus - list and show
        $this->addSql("SELECT app.add_role('ROLE_GIFT_LIST_ITEM_STATUS_LIST', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_GIFT_LIST_ITEM_STATUS_SHOW', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");

        // GiftListType - list and show
        $this->addSql("SELECT app.add_role('ROLE_GIFT_LIST_TYPE_LIST', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_GIFT_LIST_TYPE_SHOW', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");

        // Language - list and show
        $this->addSql("SELECT app.add_role('ROLE_LANGUAGE_LIST', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_LANGUAGE_SHOW', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
    }

    public function down(Schema $schema): void
    {
        // Note: Roles are not removed in down migration to preserve data integrity
        // If you need to remove these roles, do it manually
        $this->addSql('-- Roles not removed automatically. Remove manually if needed.');
    }
}
