<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120184838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add MikroTik wifi station devices and stats tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mikrotik_devices (id INT AUTO_INCREMENT NOT NULL, building_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, host VARCHAR(255) NOT NULL, port INT DEFAULT 8728 NOT NULL, use_ssl TINYINT(1) DEFAULT 0 NOT NULL, username VARCHAR(100) NOT NULL, password VARCHAR(255) NOT NULL, wireless_interface VARCHAR(50) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, last_polled_at DATETIME DEFAULT NULL, last_status VARCHAR(20) DEFAULT \'pending\' NOT NULL, last_error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_162539494D2A7E12 (building_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE mikrotik_stats (id BIGINT AUTO_INCREMENT NOT NULL, device_id INT NOT NULL, polled_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, error_message LONGTEXT DEFAULT NULL, interface VARCHAR(50) DEFAULT NULL, remote_mac VARCHAR(17) DEFAULT NULL, remote_name VARCHAR(100) DEFAULT NULL, signal_strength SMALLINT DEFAULT NULL, tx_signal_strength SMALLINT DEFAULT NULL, signal_to_noise SMALLINT DEFAULT NULL, tx_ccq SMALLINT DEFAULT NULL, rx_ccq SMALLINT DEFAULT NULL, tx_rate VARCHAR(50) DEFAULT NULL, rx_rate VARCHAR(50) DEFAULT NULL, tx_rate_mbps DOUBLE PRECISION DEFAULT NULL, rx_rate_mbps DOUBLE PRECISION DEFAULT NULL, uptime_seconds INT DEFAULT NULL, raw JSON DEFAULT NULL, INDEX IDX_3B7F310994A4C7D4 (device_id), INDEX idx_mikrotik_stats_device_time (device_id, polled_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE mikrotik_devices ADD CONSTRAINT FK_162539494D2A7E12 FOREIGN KEY (building_id) REFERENCES buildings (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE mikrotik_stats ADD CONSTRAINT FK_3B7F310994A4C7D4 FOREIGN KEY (device_id) REFERENCES mikrotik_devices (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mikrotik_devices DROP FOREIGN KEY FK_162539494D2A7E12');
        $this->addSql('ALTER TABLE mikrotik_stats DROP FOREIGN KEY FK_3B7F310994A4C7D4');
        $this->addSql('DROP TABLE mikrotik_devices');
        $this->addSql('DROP TABLE mikrotik_stats');
    }
}
