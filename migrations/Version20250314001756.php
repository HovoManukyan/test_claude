<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250314001756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE banner (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, type VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, image VARCHAR(255) DEFAULT NULL, button_text VARCHAR(255) NOT NULL, promo_text TEXT DEFAULT NULL, button_link VARCHAR(255) NOT NULL, pages JSONB NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE game (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, pandascore_id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, match JSONB DEFAULT NULL, map JSONB DEFAULT NULL, begin_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, winner JSONB DEFAULT NULL, rounds JSONB DEFAULT NULL, rounds_score JSONB DEFAULT NULL, status VARCHAR(50) NOT NULL, data JSONB DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tournament_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232B318CE008438A ON game (pandascore_id)');
        $this->addSql('CREATE INDEX IDX_232B318C33D1A3E7 ON game (tournament_id)');
        $this->addSql('CREATE TABLE team_game (game_id INT NOT NULL, team_id INT NOT NULL, PRIMARY KEY(game_id, team_id))');
        $this->addSql('CREATE INDEX IDX_F2CAC5F7E48FD905 ON team_game (game_id)');
        $this->addSql('CREATE INDEX IDX_F2CAC5F7296CD8AE ON team_game (team_id)');
        $this->addSql('CREATE TABLE player (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, pandascore_id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, nationality VARCHAR(5) DEFAULT NULL, slug VARCHAR(255) NOT NULL, image VARCHAR(255) DEFAULT NULL, birthday DATE DEFAULT NULL, age INT DEFAULT NULL, crosshair JSONB DEFAULT NULL, socials JSONB NOT NULL, bio TEXT DEFAULT NULL, total_won NUMERIC(12, 4) DEFAULT NULL, stats JSONB DEFAULT NULL, last_games JSONB DEFAULT NULL, current_team_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_98197A65E008438A ON player (pandascore_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_98197A65989D9B62 ON player (slug)');
        $this->addSql('CREATE INDEX IDX_98197A6583615D2B ON player (current_team_id)');
        $this->addSql('CREATE TABLE player_teams (player_id INT NOT NULL, team_id INT NOT NULL, PRIMARY KEY(player_id, team_id))');
        $this->addSql('CREATE INDEX IDX_29B0591E99E6F5DF ON player_teams (player_id)');
        $this->addSql('CREATE INDEX IDX_29B0591E296CD8AE ON player_teams (team_id)');
        $this->addSql('CREATE TABLE player_skins (player_id INT NOT NULL, skin_id INT NOT NULL, PRIMARY KEY(player_id, skin_id))');
        $this->addSql('CREATE INDEX IDX_5E71F04699E6F5DF ON player_skins (player_id)');
        $this->addSql('CREATE INDEX IDX_5E71F046F404637F ON player_skins (skin_id)');
        $this->addSql('CREATE TABLE skin (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, name VARCHAR(255) NOT NULL, color VARCHAR(7) NOT NULL, image_id INT DEFAULT NULL, skin_link VARCHAR(255) DEFAULT NULL, price DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE team (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, pandascore_id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, acronym VARCHAR(255) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, bio TEXT DEFAULT NULL, socials JSONB NOT NULL, location VARCHAR(5) DEFAULT NULL, stats JSONB DEFAULT NULL, last_games JSONB DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C4E0A61FE008438A ON team (pandascore_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C4E0A61F989D9B62 ON team (slug)');
        $this->addSql('CREATE TABLE tournaments (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, tournament_id INT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) DEFAULT NULL, begin_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, country VARCHAR(2) DEFAULT NULL, detailed_stats BOOLEAN NOT NULL, has_bracket BOOLEAN NOT NULL, league_id INT NOT NULL, league JSON DEFAULT NULL, live_supported BOOLEAN NOT NULL, matches JSON DEFAULT NULL, expected_roster JSON DEFAULT NULL, parsed_teams JSON DEFAULT NULL, prizepool VARCHAR(255) DEFAULT NULL, region VARCHAR(255) DEFAULT NULL, serie_id INT NOT NULL, serie JSON DEFAULT NULL, tier VARCHAR(255) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, winner_id INT DEFAULT NULL, winner_type VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E4BCFAC333D1A3E7 ON tournaments (tournament_id)');
        $this->addSql('CREATE TABLE player_tournaments (tournament_id INT NOT NULL, player_id INT NOT NULL, PRIMARY KEY(tournament_id, player_id))');
        $this->addSql('CREATE INDEX IDX_BC8B135633D1A3E7 ON player_tournaments (tournament_id)');
        $this->addSql('CREATE INDEX IDX_BC8B135699E6F5DF ON player_tournaments (player_id)');
        $this->addSql('CREATE TABLE team_tournaments (tournament_id INT NOT NULL, team_id INT NOT NULL, PRIMARY KEY(tournament_id, team_id))');
        $this->addSql('CREATE INDEX IDX_F8C158E33D1A3E7 ON team_tournaments (tournament_id)');
        $this->addSql('CREATE INDEX IDX_F8C158E296CD8AE ON team_tournaments (team_id)');
        $this->addSql('CREATE TABLE "user" (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE game ADD CONSTRAINT FK_232B318C33D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team_game ADD CONSTRAINT FK_F2CAC5F7E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team_game ADD CONSTRAINT FK_F2CAC5F7296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A6583615D2B FOREIGN KEY (current_team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE player_teams ADD CONSTRAINT FK_29B0591E99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE player_teams ADD CONSTRAINT FK_29B0591E296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE player_skins ADD CONSTRAINT FK_5E71F04699E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE player_skins ADD CONSTRAINT FK_5E71F046F404637F FOREIGN KEY (skin_id) REFERENCES skin (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE player_tournaments ADD CONSTRAINT FK_BC8B135633D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE player_tournaments ADD CONSTRAINT FK_BC8B135699E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team_tournaments ADD CONSTRAINT FK_F8C158E33D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team_tournaments ADD CONSTRAINT FK_F8C158E296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE player_game DROP CONSTRAINT player_game_pkey');
        $this->addSql('ALTER TABLE player_game ADD CONSTRAINT FK_813161BFE48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE player_game ADD CONSTRAINT FK_813161BF99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_813161BFE48FD905 ON player_game (game_id)');
        $this->addSql('CREATE INDEX IDX_813161BF99E6F5DF ON player_game (player_id)');
        $this->addSql('ALTER TABLE player_game ADD PRIMARY KEY (game_id, player_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game DROP CONSTRAINT FK_232B318C33D1A3E7');
        $this->addSql('ALTER TABLE team_game DROP CONSTRAINT FK_F2CAC5F7E48FD905');
        $this->addSql('ALTER TABLE team_game DROP CONSTRAINT FK_F2CAC5F7296CD8AE');
        $this->addSql('ALTER TABLE player DROP CONSTRAINT FK_98197A6583615D2B');
        $this->addSql('ALTER TABLE player_teams DROP CONSTRAINT FK_29B0591E99E6F5DF');
        $this->addSql('ALTER TABLE player_teams DROP CONSTRAINT FK_29B0591E296CD8AE');
        $this->addSql('ALTER TABLE player_skins DROP CONSTRAINT FK_5E71F04699E6F5DF');
        $this->addSql('ALTER TABLE player_skins DROP CONSTRAINT FK_5E71F046F404637F');
        $this->addSql('ALTER TABLE player_tournaments DROP CONSTRAINT FK_BC8B135633D1A3E7');
        $this->addSql('ALTER TABLE player_tournaments DROP CONSTRAINT FK_BC8B135699E6F5DF');
        $this->addSql('ALTER TABLE team_tournaments DROP CONSTRAINT FK_F8C158E33D1A3E7');
        $this->addSql('ALTER TABLE team_tournaments DROP CONSTRAINT FK_F8C158E296CD8AE');
        $this->addSql('DROP TABLE banner');
        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE team_game');
        $this->addSql('DROP TABLE player');
        $this->addSql('DROP TABLE player_teams');
        $this->addSql('DROP TABLE player_skins');
        $this->addSql('DROP TABLE skin');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE tournaments');
        $this->addSql('DROP TABLE player_tournaments');
        $this->addSql('DROP TABLE team_tournaments');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('ALTER TABLE player_game DROP CONSTRAINT FK_813161BFE48FD905');
        $this->addSql('ALTER TABLE player_game DROP CONSTRAINT FK_813161BF99E6F5DF');
        $this->addSql('DROP INDEX IDX_813161BFE48FD905');
        $this->addSql('DROP INDEX IDX_813161BF99E6F5DF');
        $this->addSql('DROP INDEX player_game_pkey');
        $this->addSql('ALTER TABLE player_game ADD PRIMARY KEY (player_id, game_id)');
    }
}
