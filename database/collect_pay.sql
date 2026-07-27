-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 27 juil. 2026 à 17:37
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `collect_pay`
--

-- --------------------------------------------------------

--
-- Structure de la table `actes_generateurs`
--

CREATE TABLE `actes_generateurs` (
  `id` int(11) NOT NULL,
  `secteur_id` int(11) NOT NULL,
  `libelle` text NOT NULL,
  `code_acte_generateur` varchar(100) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `alertes_systeme`
--

CREATE TABLE `alertes_systeme` (
  `id` int(11) NOT NULL,
  `type_alerte` varchar(100) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `niveau` enum('faible','moyen','critique') DEFAULT 'moyen',
  `statut` enum('ouverte','traitee') DEFAULT 'ouverte',
  `date_detection` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `amr`
--

CREATE TABLE `amr` (
  `id` int(11) NOT NULL,
  `numero_amr` varchar(100) NOT NULL,
  `reference_type` enum('NP','NPF') NOT NULL,
  `reference_numero` varchar(100) NOT NULL,
  `note_perception_id` int(11) NOT NULL,
  `montant_principal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `montant_penalite` decimal(18,2) NOT NULL DEFAULT 0.00,
  `montant_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `jours_retard` int(11) NOT NULL DEFAULT 0,
  `statut` enum('emis','valide','rejete') DEFAULT 'emis',
  `motif` text DEFAULT NULL,
  `user_emission_id` int(11) DEFAULT NULL,
  `user_validation_id` int(11) DEFAULT NULL,
  `date_emission` datetime DEFAULT current_timestamp(),
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `amr`
--

INSERT INTO `amr` (`id`, `numero_amr`, `reference_type`, `reference_numero`, `note_perception_id`, `montant_principal`, `montant_penalite`, `montant_total`, `jours_retard`, `statut`, `motif`, `user_emission_id`, `user_validation_id`, `date_emission`, `date_validation`) VALUES
(1, 'NP-BU-CPR-26-000008', 'NP', 'NP-BU-CPR-26-000008', 32, 5250000.00, 525000.00, 5775000.00, 23, 'valide', 'AMR émis pour dépassement de l\'échéance de paiement.', 3, 3, '2026-06-13 16:20:21', '2026-06-13 16:20:32'),
(2, 'NP-BU-CPR-26-000014', 'NP', 'NP-BU-CPR-26-000014', 3, 21258000.00, 2125800.00, 23383800.00, 23, 'valide', 'AMR émis pour dépassement de l\'échéance de paiement.', 3, 3, '2026-06-15 16:56:26', '2026-06-15 16:56:34'),
(3, 'NP-BU-CPR-26-000016', 'NP', 'NP-BU-CPR-26-000016', 7, 5325600.00, 532560.00, 5858160.00, 22, 'valide', 'AMR émis pour dépassement de l\'échéance de paiement.', 3, 3, '2026-06-27 02:26:10', '2026-06-27 02:26:19');

-- --------------------------------------------------------

--
-- Structure de la table `apurements`
--

CREATE TABLE `apurements` (
  `id` int(11) NOT NULL,
  `reference_type` enum('NP','FRACTION') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `montant_du` decimal(18,2) NOT NULL,
  `montant_paye` decimal(18,2) NOT NULL,
  `penalite_validee` decimal(18,2) DEFAULT 0.00,
  `solde_restant` decimal(18,2) NOT NULL,
  `statut` enum('partiel','total') NOT NULL,
  `date_apurement` date NOT NULL,
  `user_apurement_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `apurements`
--

INSERT INTO `apurements` (`id`, `reference_type`, `reference_id`, `montant_du`, `montant_paye`, `penalite_validee`, `solde_restant`, `statut`, `date_apurement`, `user_apurement_id`, `created_at`) VALUES
(1, 'NP', 2, 266000000.00, 266000000.00, 0.00, 0.00, 'total', '2026-06-15', 3, '2026-06-15 14:02:05'),
(2, 'NP', 3, 21258000.00, 21258000.00, 0.00, 0.00, 'total', '2026-06-15', 3, '2026-06-15 15:14:19'),
(3, 'FRACTION', 5, 500000.00, 500000.00, 0.00, 0.00, 'total', '2026-06-26', 3, '2026-06-26 17:48:21'),
(4, 'FRACTION', 6, 220000.00, 220000.00, 0.00, 0.00, 'total', '2026-06-26', 3, '2026-06-26 17:50:22'),
(10, 'NP', 4, 720000.00, 720000.00, 0.00, 0.00, 'total', '2026-06-26', 3, '2026-06-26 21:29:58'),
(11, 'NP', 8, 386400.00, 386400.00, 0.00, 0.00, 'total', '2026-06-27', 19, '2026-06-27 16:21:02'),
(12, 'NP', 9, 26700027.00, 26700027.00, 0.00, 0.00, 'total', '2026-06-27', 3, '2026-06-27 17:46:46');

-- --------------------------------------------------------

--
-- Structure de la table `articles_budgetaires`
--

CREATE TABLE `articles_budgetaires` (
  `id` int(11) NOT NULL,
  `code_article` varchar(50) NOT NULL,
  `secteur` varchar(150) DEFAULT NULL,
  `nature_acte` text NOT NULL,
  `fait_generateur` text DEFAULT NULL,
  `periodicite` enum('ponctuelle','mensuelle','trimestrielle','semestrielle','annuelle','non_renouvelable') DEFAULT 'ponctuelle',
  `type_taux` enum('fixe','pourcentage','mixte') NOT NULL,
  `taux_acte` decimal(18,6) DEFAULT 0.000000,
  `frais_administratif` decimal(18,6) DEFAULT 0.000000,
  `frais_technique` decimal(18,6) DEFAULT 0.000000,
  `unite` varchar(50) DEFAULT NULL,
  `devise_base` varchar(10) DEFAULT 'USD',
  `formule_personnalisee` text DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `direction_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `art_par` varchar(100) DEFAULT NULL,
  `acte_generateur` text DEFAULT NULL,
  `mode_calcul` enum('fixe','par_unite','pourcentage','mixte','formule') DEFAULT 'fixe',
  `unite_assiette` varchar(100) DEFAULT NULL,
  `base_calcul_libelle` varchar(150) DEFAULT NULL,
  `rapportable` tinyint(1) DEFAULT 1,
  `libelle_taux` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `articles_budgetaires`
--

INSERT INTO `articles_budgetaires` (`id`, `code_article`, `secteur`, `nature_acte`, `fait_generateur`, `periodicite`, `type_taux`, `taux_acte`, `frais_administratif`, `frais_technique`, `unite`, `devise_base`, `formule_personnalisee`, `actif`, `created_at`, `direction_id`, `service_id`, `art_par`, `acte_generateur`, `mode_calcul`, `unite_assiette`, `base_calcul_libelle`, `rapportable`, `libelle_taux`) VALUES
(1, '17111600', 'FINANCE', 'Impôts sur les revenus locatifs (IRL)', 'Contrat de bail', 'annuelle', 'pourcentage', 10.000000, 0.000000, 0.000000, 'Loyer mensuel', '%', 'MontantLoyer × NombreMois × 10 / 100', 1, '2026-06-14 20:44:34', 1, 2, 'Art.17111600', 'CONTRAT DE BAIL', 'pourcentage', 'Montant du loyer', 'Loyer mensuel × Nombre de mois', 1, 'a) Personne physique non commerçante'),
(2, '17111600', 'FINANCE', 'Impôts sur les revenus locatifs (IRL)', 'Contrat de bail', 'annuelle', 'pourcentage', 15.000000, 0.000000, 0.000000, 'Loyer mensuel', '%', 'MontantLoyer × NombreMois × 15 / 100', 1, '2026-06-14 20:46:12', 1, 2, 'Art.17111600', 'CONTRAT DE BAIL', 'pourcentage', 'Montant du loyer', 'Loyer mensuel × Nombre de mois', 1, 'b) Taux IRL personne commerçante / morale'),
(4, '17111600', 'FINANCE', 'RETENU LOCATIVE (RL)', 'Contrat de bail', 'annuelle', 'pourcentage', 2.000000, 0.000000, 0.000000, 'Loyer mensuel', '%', 'MontantLoyer × NombreMois × 0 / 100', 1, '2026-06-14 20:51:59', 1, 2, 'Art.17111600', 'CONTRAT DE BAIL', 'pourcentage', 'Montant du loyer', 'Taux RL physique non commerçante', 1, 'a) Personne physique non commerçante'),
(5, '37444100', 'ECONOMIE', 'Produits d’amendes sur la législation des prix et\r\ndans le commerce de détail', 'Constat d\'infraction', 'ponctuelle', 'pourcentage', 100.000000, 0.000000, 0.000000, 'Amende', '%', '100% du Montant', 1, '2026-06-14 20:58:20', 2, 2, 'Art. 37444100', 'Constat d\'infraction', 'pourcentage', 'Amende', '100% du Montant d\'Amende', 1, '---------------------------------------'),
(6, '17153140', 'COMMERCE', 'Taxe sur licence de fabrication, d\'achat et vente,\r\ndétention, du commerce et toutes opérations\r\nrelatives aux alcools, boissons alcoolisées', 'Demande de licence', 'annuelle', 'fixe', 75.000000, 5.000000, 5.000000, 'Par Licence', 'USD', 'Nombre de Licence x Le Taux d\'acte', 1, '2026-06-14 21:33:33', 2, 3, 'Art. 17153140', 'Demande de licence', 'par_unite', 'Nombre des Licences', 'Nombre des Licences', 1, '1. Taxe sur permis d\'importation'),
(7, '17153140', 'COMMERCE', 'Taxe sur licence de fabrication, d\'achat et vente,\r\ndétention, du commerce et toutes opérations\r\nrelatives aux alcools, boissons alcoolisées.', 'Demande de licence', 'ponctuelle', 'fixe', 1400000.000000, NULL, NULL, 'CERTIFICAT', 'CDF', 'Quantite x par Taux de l\'Acte', 1, '2026-06-15 00:10:30', 2, 3, 'Art. 17153140', 'Demande de licence', 'par_unite', 'Certificat', 'Nombre des Licences', 1, '---------------------------------------'),
(8, '27022450', 'ENVIRONNEMENT', 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'Mise sur le marché des matières non biodégradables', 'ponctuelle', 'fixe', 28.000000, 2800.000000, 2800.000000, '/Carte', 'CDF', '----', 1, '2026-06-15 18:53:51', 2, 4, 'aRT. 27022450', 'Mise sur le marché des\r\nmatières non\r\nbiodégradables', 'fixe', 'Nombre', '----', 1, 'a) Carte prépayée'),
(9, '27022450', 'ENVIRONNEMENT', 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'Mise sur le marché des matières non biodégradables', 'ponctuelle', 'pourcentage', 2.000000, 0.000000, 0.000000, '/Carte', '%', '2% de la Valeur', 1, '2026-06-15 18:56:27', 2, 4, 'aRT. 27022450', 'Mise sur le marché des\r\nmatières non\r\nbiodégradables', 'pourcentage', 'Nombre', '----', 1, 'b) Plastique (babouches, chaussures, matelas, mousse, chaise, table, étagère et autres plastiques)');

-- --------------------------------------------------------

--
-- Structure de la table `articles_budgetaires_new`
--

CREATE TABLE `articles_budgetaires_new` (
  `id` int(11) NOT NULL,
  `acte_generateur_id` int(11) NOT NULL,
  `code_article` varchar(100) NOT NULL,
  `libelle_article` text NOT NULL,
  `art_par` varchar(100) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `article_taux_province`
--

CREATE TABLE `article_taux_province` (
  `id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `province_id` int(11) NOT NULL,
  `devise` enum('USD','CDF') DEFAULT 'USD',
  `taux_acte` decimal(18,6) DEFAULT 0.000000,
  `frais_administratif` decimal(18,6) DEFAULT 0.000000,
  `frais_technique` decimal(18,6) DEFAULT 0.000000,
  `taux_total` decimal(18,6) DEFAULT 0.000000,
  `taux_pourcentage` decimal(10,6) DEFAULT 0.000000,
  `frais_admin_type` enum('fixe','pourcentage_de_taxe','aucun') DEFAULT 'fixe',
  `frais_tech_type` enum('fixe','pourcentage_de_taxe','aucun') DEFAULT 'fixe',
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` text NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(100) DEFAULT NULL,
  `reference_document` varchar(150) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `table_modifiee` varchar(100) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `ancienne_valeur` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ancienne_valeur`)),
  `nouvelle_valeur` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`nouvelle_valeur`)),
  `ip_address` varchar(100) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `date_action` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `module`, `reference_document`, `details`, `table_modifiee`, `reference_id`, `ancienne_valeur`, `nouvelle_valeur`, `ip_address`, `user_agent`, `date_action`, `created_at`) VALUES
(1, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 09:30:35', '2026-06-22 06:17:40'),
(2, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000001', 'Paiement de 1 400 000,00 CDF enregistré. Référence : BV0001', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 09:32:33', '2026-06-22 06:17:40'),
(3, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000001', 'Paiement de 1 400 000,00 CDF enregistré. Référence : BV0001-1', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 09:34:20', '2026-06-22 06:17:40'),
(4, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 09:34:46', '2026-06-22 06:17:40'),
(5, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 09:48:56', '2026-06-22 06:17:40'),
(6, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 09:49:05', '2026-06-22 06:17:40'),
(7, 3, 'IMPRESSION_DOCUMENT', NULL, 'ND', 'ND-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 09:49:08', '2026-06-22 06:17:40'),
(8, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 09:53:50', '2026-06-22 06:17:40'),
(9, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 09:53:54', '2026-06-22 06:17:40'),
(10, 3, 'IMPRESSION_DOCUMENT', NULL, 'ND', 'ND-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 09:53:57', '2026-06-22 06:17:40'),
(11, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 10:10:17', '2026-06-22 06:17:40'),
(12, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 10:10:21', '2026-06-22 06:17:40'),
(13, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 10:19:19', '2026-06-22 06:17:40'),
(14, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 10:22:53', '2026-06-22 06:17:40'),
(15, 3, 'IMPRESSION_DOCUMENT', NULL, 'ND', 'ND-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 10:23:00', '2026-06-22 06:17:40'),
(16, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 10:48:12', '2026-06-22 06:17:40'),
(17, 3, 'IMPRESSION_DOCUMENT', NULL, 'ND', 'ND-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 10:48:49', '2026-06-22 06:17:40'),
(18, 3, 'IMPRESSION_DOCUMENT', NULL, 'ND', 'ND-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 10:54:58', '2026-06-22 06:17:40'),
(19, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 10:56:42', '2026-06-22 06:17:40'),
(20, 3, 'IMPRESSION_DOCUMENT', NULL, 'ND', 'ND-BU-CPR-26-000001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-05 12:23:23', '2026-06-22 06:17:40'),
(21, 3, 'IMPRESSION_DOCUMENT', NULL, 'ND', 'ND-BU-CPR-26-000002', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 15:08:29', '2026-06-22 06:17:40'),
(22, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000002', 'Création de la NP depuis la ND ND-BU-CPR-26-000002 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 15:10:12', '2026-06-22 06:17:40'),
(23, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000002', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 15:10:21', '2026-06-22 06:17:40'),
(24, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000002', 'Paiement de 1 820 000,00 CDF enregistré. Référence : BV00013', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 15:11:17', '2026-06-22 06:17:40'),
(25, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000002', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 15:18:54', '2026-06-22 06:17:40'),
(26, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000002', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 15:20:26', '2026-06-22 06:17:40'),
(27, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000002', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 15:52:13', '2026-06-22 06:17:40'),
(28, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000003', 'Création de la NP depuis la ND ND-BU-CPR-26-000003 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 15:55:07', '2026-06-22 06:17:40'),
(29, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000003', 'Paiement de 66 500 000,00 CDF enregistré. Référence : BV00014', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 15:57:24', '2026-06-22 06:17:40'),
(30, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000004', 'Création de la NP depuis la ND ND-BU-CPR-26-000004 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 16:07:01', '2026-06-22 06:17:40'),
(31, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000004', 'Paiement de 26 600 000,00 CDF enregistré. Référence : BV00015', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 16:07:47', '2026-06-22 06:17:40'),
(32, 3, 'IMPRESSION_DOCUMENT', NULL, 'QUITTANCE', 'QT-BU-CPR-26-000002', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 16:49:38', '2026-06-22 06:17:40'),
(33, 3, 'IMPRESSION_DOCUMENT', NULL, 'QUITTANCE', 'QT-BU-CPR-26-000003', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 17:02:01', '2026-06-22 06:17:40'),
(34, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000005', 'Création de la NP depuis la ND ND-BU-CPR-26-000005 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 17:04:16', '2026-06-22 06:17:40'),
(35, 3, 'Création avis de fractionnement', NULL, 'Ordonnancement', 'AVF-BU-CPR-26-000001', 'Avis créé pour la NP mère NP-BU-CPR-26-000005', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 17:04:41', '2026-06-22 06:17:40'),
(36, 3, 'IMPRESSION_DOCUMENT', NULL, 'NPF', 'NP-BU-CPR-26-000005-001', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 17:14:44', '2026-06-22 06:17:40'),
(37, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000005-001', 'Paiement de 30 000 000,00 CDF enregistré. Référence : BV00014', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 17:57:33', '2026-06-22 06:17:40'),
(38, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000006', 'Création de la NP depuis la ND ND-BU-CPR-26-000006 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 18:21:28', '2026-06-22 06:17:40'),
(39, 3, 'Création avis de fractionnement', NULL, 'Ordonnancement', 'AVF-BU-CPR-26-000002', 'Avis créé pour la NP mère NP-BU-CPR-26-000006', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 18:21:44', '2026-06-22 06:17:40'),
(40, 3, 'IMPRESSION_DOCUMENT', NULL, 'NPF', 'NP-BU-CPR-26-000006-002', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 18:22:21', '2026-06-22 06:17:40'),
(41, 3, 'IMPRESSION_DOCUMENT', NULL, 'NPF', 'NP-BU-CPR-26-000006-002', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 18:30:40', '2026-06-22 06:17:40'),
(42, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000006-001', 'Paiement de 25 000 000,00 CDF enregistré. Référence : BV00016-1', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 18:33:25', '2026-06-22 06:17:40'),
(43, 3, 'IMPRESSION_DOCUMENT', NULL, 'NPF', 'NP-BU-CPR-26-000006-002', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 18:45:49', '2026-06-22 06:17:40'),
(44, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000006-002', 'Paiement de 10 000 000,00 CDF enregistré. Référence : BV00016-2', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 19:01:54', '2026-06-22 06:17:40'),
(45, 3, 'IMPRESSION_DOCUMENT', NULL, 'QUITTANCE', 'QT-BU-CPR-26-000005', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 19:17:20', '2026-06-22 06:17:40'),
(46, 3, 'IMPRESSION_DOCUMENT', NULL, 'QUITTANCE', 'QT-BU-CPR-26-000005', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 19:18:46', '2026-06-22 06:17:40'),
(47, 3, 'IMPRESSION_DOCUMENT', NULL, 'QUITTANCE', 'QT-BU-CPR-26-000005', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-12 19:19:29', '2026-06-22 06:17:40'),
(48, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000007', 'Création de la NP depuis la ND ND-BU-CPR-26-000007 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 12:30:14', '2026-06-22 06:17:40'),
(49, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000007', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 12:30:20', '2026-06-22 06:17:40'),
(50, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000007', 'Paiement de 52 500 000,00 CDF enregistré. Référence : BV00013', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 12:38:31', '2026-06-22 06:17:40'),
(51, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000008', 'Création de la NP depuis la ND ND-BU-CPR-26-000008 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 14:17:11', '2026-06-22 06:17:40'),
(52, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000008', 'Paiement de 5 250 000,00 CDF enregistré. Référence : BV0001-1', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 14:21:33', '2026-06-22 06:17:40'),
(53, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000009', 'Création de la NP depuis la ND ND-BU-CPR-26-000009 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 14:42:25', '2026-06-22 06:17:40'),
(54, 3, 'IMPRESSION_DOCUMENT', NULL, 'NP', 'NP-BU-CPR-26-000009', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 14:42:32', '2026-06-22 06:17:40'),
(55, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000010', 'Création de la NP depuis la ND ND-BU-CPR-26-000010 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 15:51:31', '2026-06-22 06:17:40'),
(56, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000011', 'Création de la NP depuis la ND ND-BU-CPR-26-000011 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 15:55:31', '2026-06-22 06:17:40'),
(57, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000012', 'Création de la NP depuis la ND ND-BU-CPR-26-000012 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-15 13:28:17', '2026-06-22 06:17:40'),
(58, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000013', 'Création de la NP depuis la ND ND-BU-CPR-26-000013 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-15 14:00:24', '2026-06-22 06:17:40'),
(59, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000013', 'Paiement de 266 000 000,00 CDF enregistré. Référence : BV0001-1', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-15 14:01:19', '2026-06-22 06:17:40'),
(60, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000012', 'Paiement de 3 178 000,00 CDF enregistré. Référence : BV0001-1', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-15 14:29:43', '2026-06-22 06:17:40'),
(61, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000014', 'Création de la NP depuis la ND ND-BU-CPR-26-000014 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-15 14:51:48', '2026-06-22 06:17:40'),
(62, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000014', 'Paiement de 21 258 000,00 CDF enregistré. Référence : BV00014', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-15 15:13:57', '2026-06-22 06:17:40'),
(63, 3, 'IMPRESSION_DOCUMENT', NULL, 'QUITTANCE', 'QT-BU-CPR-26-000006', 'Impression ou réimpression du document', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-15 15:14:28', '2026-06-22 06:17:40'),
(64, 3, 'toggle_status', 'Changement statut utilisateur BOSMIL MENDE Joseph => actif', 'users', NULL, NULL, NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-22 06:17:52', '2026-06-22 06:17:52'),
(65, 3, 'toggle_status', 'Changement statut utilisateur ZOE LOMBO => inactif', 'users', NULL, NULL, NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-22 06:17:57', '2026-06-22 06:17:57'),
(66, 3, 'toggle_status', 'Changement statut utilisateur ZOE LOMBO => actif', 'users', NULL, NULL, NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-22 06:18:12', '2026-06-22 06:18:12'),
(67, 3, 'edit', 'Modification utilisateur : BOSMIL MENDE Josephe', 'users', NULL, NULL, NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-22 06:21:24', '2026-06-22 06:21:24'),
(68, 3, 'permissions', 'Mise à jour permissions rôle : SUPER_ADMIN', 'roles', NULL, NULL, NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-22 07:19:46', '2026-06-22 07:19:46'),
(69, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000015', 'Création de la NP depuis la ND ND-BU-CPR-26-000015 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-26 17:45:46', '2026-06-26 17:45:46'),
(70, 3, 'Création avis de fractionnement', NULL, 'Ordonnancement', 'AVF-BU-CPR-26-000003', 'Avis créé pour la NP mère NP-BU-CPR-26-000015', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-26 17:46:47', '2026-06-26 17:46:47'),
(71, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000015-001', 'Paiement de 500 000,00 CDF enregistré. Référence : BV0001-1', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-26 17:48:21', '2026-06-26 17:48:21'),
(72, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000015-002', 'Paiement de 220 000,00 CDF enregistré. Référence : BV0001-2', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-26 17:50:22', '2026-06-26 17:50:22'),
(73, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000016', 'Création de la NP depuis la ND ND-BU-CPR-26-000016 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 00:20:46', '2026-06-27 00:20:46'),
(74, 3, 'add', 'Création utilisateur : UTSHUDI LOFUMA Jean', 'users', NULL, NULL, NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 01:40:08', '2026-06-27 01:40:08'),
(75, 3, 'permissions', 'Mise à jour permissions rôle : APUREUR', 'roles', NULL, NULL, NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 01:52:56', '2026-06-27 01:52:56'),
(76, 7, 'Création NP globale', NULL, 'Ordonnancement', 'NP-TS-KIS-26-000001', 'Création de la NP depuis la ND ND-TS-KIS-26-000004 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 16:04:06', '2026-06-27 16:04:06'),
(77, 20, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-TS-KIS-26-000001', 'Paiement de 386 400,00 CDF enregistré. Référence : BV0001-1', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 16:21:02', '2026-06-27 16:21:02'),
(78, 3, 'Création NP globale', NULL, 'Ordonnancement', 'NP-BU-CPR-26-000017', 'Création de la NP depuis la ND ND-BU-CPR-26-000017 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 17:46:21', '2026-06-27 17:46:21'),
(79, 3, 'Paiement enregistré', NULL, 'Recouvrement', 'NP-BU-CPR-26-000017', 'Paiement de 26 700 027,00 CDF enregistré. Référence : BV00014', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 17:46:46', '2026-06-27 17:46:46'),
(80, 7, 'Création NP globale', NULL, 'Ordonnancement', 'NP-TS-KIS-26-000002', 'Création de la NP depuis la ND ND-TS-KIS-26-000006 avec répartition bancaire.', NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-29 16:31:47', '2026-06-29 16:31:47');

-- --------------------------------------------------------

--
-- Structure de la table `avis_fractionnement`
--

CREATE TABLE `avis_fractionnement` (
  `id` int(11) NOT NULL,
  `numero_avis` varchar(80) NOT NULL,
  `note_perception_id` int(11) NOT NULL,
  `date_avis` date NOT NULL,
  `nombre_fractions` int(11) NOT NULL,
  `statut` varchar(50) DEFAULT 'accorde',
  `user_recouvrement_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `autorite_type` varchar(50) DEFAULT NULL,
  `autorite_nom` varchar(150) DEFAULT NULL,
  `annotation` text DEFAULT NULL,
  `nombre_tranches` int(11) DEFAULT 1,
  `montant_total` decimal(18,2) DEFAULT 0.00,
  `user_directeur_recouvrement_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `avis_fractionnement`
--

INSERT INTO `avis_fractionnement` (`id`, `numero_avis`, `note_perception_id`, `date_avis`, `nombre_fractions`, `statut`, `user_recouvrement_id`, `created_at`, `autorite_type`, `autorite_nom`, `annotation`, `nombre_tranches`, `montant_total`, `user_directeur_recouvrement_id`) VALUES
(1, 'AVF-BU-CPR-26-000003', 4, '2026-06-26', 0, 'accorde', NULL, '2026-06-26 17:46:46', 'DG', 'DADDY NGANDU', 'ACCORDER', 2, 720000.00, 3);

-- --------------------------------------------------------

--
-- Structure de la table `centres`
--

CREATE TABLE `centres` (
  `id` int(11) NOT NULL,
  `province_id` int(11) NOT NULL,
  `nom` varchar(150) NOT NULL,
  `code_centre` varchar(50) NOT NULL,
  `code_centre_short` varchar(10) NOT NULL,
  `adresse` text DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `centres`
--

INSERT INTO `centres` (`id`, `province_id`, `nom`, `code_centre`, `code_centre_short`, `adresse`, `actif`, `created_at`) VALUES
(1, 1, 'Centre Principal', 'CENTRE-PRINCIPAL', 'CPR', 'Kinshasa', 1, '2026-05-31 20:59:28'),
(3, 2, 'DIRECTION GENERALE', 'DG-KIS', 'KIS', 'C/MAKISO, Q/MUSIBASIBA N04', 1, '2026-05-31 22:16:32');

-- --------------------------------------------------------

--
-- Structure de la table `comptes_bancaires`
--

CREATE TABLE `comptes_bancaires` (
  `id` int(11) NOT NULL,
  `province_id` int(11) DEFAULT NULL,
  `centre_id` int(11) DEFAULT NULL,
  `banque` varchar(150) NOT NULL,
  `numero_compte` varchar(150) NOT NULL,
  `devise` enum('CDF','USD') DEFAULT 'CDF',
  `intitule_compte` varchar(255) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `comptes_bancaires`
--

INSERT INTO `comptes_bancaires` (`id`, `province_id`, `centre_id`, `banque`, `numero_compte`, `devise`, `intitule_compte`, `actif`, `created_at`) VALUES
(2, 2, 3, 'Rawbank', '000-RAW-CDF-001', 'CDF', 'Compte recettes provinciales', 1, '2026-06-03 22:41:03'),
(3, 2, 3, 'TMB', '000-TMB-USD-001', 'USD', 'Compte recettes provinciales', 1, '2026-06-03 22:41:03'),
(4, 2, 3, 'Equity BCDC', '000-EQB-CDF-001', 'CDF', 'Compte recettes provinciales', 1, '2026-06-03 22:41:03'),
(5, 2, 3, 'Ecobank', '000-ECO-CDF-001', 'CDF', 'Compte recettes provinciales', 1, '2026-06-03 22:41:03');

-- --------------------------------------------------------

--
-- Structure de la table `contribuables`
--

CREATE TABLE `contribuables` (
  `id` int(11) NOT NULL,
  `type_personne` enum('physique','morale','etablissement','ong','autres') NOT NULL,
  `raison_sociale` varchar(255) DEFAULT NULL,
  `nom` varchar(150) DEFAULT NULL,
  `postnom` varchar(150) DEFAULT NULL,
  `prenom` varchar(150) DEFAULT NULL,
  `nif` varchar(100) DEFAULT NULL,
  `rccm` varchar(100) DEFAULT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `ville` varchar(150) DEFAULT NULL,
  `territoire` varchar(150) DEFAULT NULL,
  `province` varchar(150) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `code_contribuable` varchar(50) DEFAULT NULL,
  `id_national` varchar(100) DEFAULT NULL,
  `telephone_secondaire` varchar(50) DEFAULT NULL,
  `commune` varchar(150) DEFAULT NULL,
  `quartier` varchar(150) DEFAULT NULL,
  `avenue` varchar(150) DEFAULT NULL,
  `numero_parcelle` varchar(50) DEFAULT NULL,
  `latitude` decimal(12,8) DEFAULT NULL,
  `longitude` decimal(12,8) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `statut` enum('actif','suspendu','radie','decede','contentieux') DEFAULT 'actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `contribuables`
--

INSERT INTO `contribuables` (`id`, `type_personne`, `raison_sociale`, `nom`, `postnom`, `prenom`, `nif`, `rccm`, `telephone`, `email`, `adresse`, `ville`, `territoire`, `province`, `actif`, `created_at`, `code_contribuable`, `id_national`, `telephone_secondaire`, `commune`, `quartier`, `avenue`, `numero_parcelle`, `latitude`, `longitude`, `photo`, `statut`) VALUES
(1, 'physique', 'RAS', 'LOMBO', 'LOFUMA', 'Jean', '000012', 'RAS', '0820646942', 'nmjpro88@gmail.com', 'KINSHASA, MONT-NGAFULA, MBUDI, BANYINDO 03', 'KINSHASA', NULL, '', 1, '2026-05-31 22:09:34', 'CTR-BU-CPR-26-000001', 'RAS', '0891269201', 'MONT-NGAFULA', 'MBUDI', 'BANYINDO', '03', 45.00000000, 24.00000000, 'CTR_1780265374.jpeg', 'actif'),
(2, 'physique', 'PP', 'BOSMIL', 'THERESE', 'Feza', '0000123', 'RAS', '0820646942', 'nmjpro88@gmail.com', 'KINSHASA, MONT-NGAFULA, MBUDI, BANYINDO 03', 'KINSHASA', NULL, '', 1, '2026-06-01 12:17:54', 'CTR-BU-CPR-26-000002', 'RAS', '', 'MONT-NGAFULA', 'MBUDI', 'BANYINDO', '03', 45.00000000, 24.00000000, 'CTR_1780316274.jpeg', 'actif'),
(3, 'morale', 'BELTEXCO', 'RAS', 'RAS', 'RAS', '00001234', '456TYR87UJ', '0820646942', 'nmjpro88@gmail.com', 'KINSHASA, MONT-NGAFULA, MBUDI, BANYINDO 03', 'KINSHASA', NULL, '', 1, '2026-06-01 18:15:32', 'CTR-BU-CPR-26-000003', '435276783H', '0820646942', 'MONT-NGAFULA', 'MBUDI', 'BANYINDO', '03', 45.00000000, 24.00000000, 'CTR_1780337732.PNG', 'actif'),
(4, 'morale', 'SOCIMEX', 'RAS', 'RAS', 'RAS', '00001234', '456TYR87UJ', '0820646942', 'nmjpro88@gmail.com', 'KINSHASA, MONT-NGAFULA, MBUDI, BANYINDO 03', 'KINSHASA', NULL, '', 1, '2026-06-01 18:21:13', 'CTR-BU-CPR-26-000004', '435276783H', '', 'MONT-NGAFULA', 'MBUDI', 'BANYINDO', '03', 45.00000000, 24.00000000, 'CTR_1780338073.PNG', 'actif'),
(5, 'morale', 'TRINIX', NULL, NULL, NULL, '000012345', '456TYR87UJ', '0820646942', 'nmjpro88@gmail.com', 'KINSHASA, MONT-NGAFULA, MBUDI, BANYINDO 03', 'KINSHASA', NULL, NULL, 1, '2026-06-02 10:07:11', 'CTR-260602120711', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif'),
(6, 'morale', 'FLYFLASH', NULL, NULL, NULL, '000012345', '456TYR87UJ', '0820646942', 'nmjpro88@gmail.com', 'KINSHASA, MONT-NGAFULA, MBUDI, BANYINDO 03', 'KINSHASA', NULL, NULL, 1, '2026-06-02 11:47:09', 'CTR-260602134709', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif'),
(7, 'physique', NULL, 'NKOSSA', 'LOFUMA', 'Thierry', '00909833DN', NULL, '0820646942', 'nmjpro88@gmail.com', 'KINSHASA, MONT-NGAFULA, MBUDI, BANYINDO 03', 'KINSHASA', NULL, NULL, 1, '2026-06-02 16:22:19', 'CTR-260602182219', 'RAS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif'),
(8, '', 'COURTIER', NULL, NULL, NULL, '000012345', '456TYR87UJ', '0820646942', 'nmjpro88@gmail.com', 'KINSHASA, MONT-NGAFULA, MBUDI, BANYINDO 03', 'KINSHASA', NULL, NULL, 1, '2026-06-02 17:19:42', 'CTR-260602191942', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif'),
(9, 'morale', 'COURTIER2', 'MENDE', 'BOSMIL', 'JOSEPH', '000012345', '456TYR87UJ', '0820646942', 'nmjpro88@gmail.com', 'KINSHASA, MONT-NGAFULA, MBUDI, BANYINDO 03', 'KINSHASA', NULL, NULL, 1, '2026-06-02 17:55:07', 'CTR-260602195507', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif'),
(10, 'physique', NULL, 'MULUBA', 'LOFUMA', 'Jean', '0000123', NULL, '0820646942', 'nmjpro88@gmail.com', 'KINSHASA, MONT-NGAFULA, MBUDI, BANYINDO 03', 'KINSHASA', NULL, NULL, 1, '2026-06-12 18:20:28', 'CTR-260612202028', '435276783H', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif'),
(11, 'morale', 'AZUS', NULL, NULL, NULL, '000012345', '456TYR87UJ', '0820646942', 'nmjpro88@gmail.com', 'KINSHASA, MONT-NGAFULA, MBUDI, BANYINDO 03', 'KINSHASA', NULL, NULL, 1, '2026-06-14 13:57:57', 'CTR-260614155757', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif'),
(12, 'physique', NULL, 'LOFUMA', 'LOFUMA', 'Jean', '000012345', NULL, '0820646942', 'nmjpro88@gmail.com', 'kinshasa', 'kinshasa', NULL, NULL, 1, '2026-06-15 14:49:33', 'CTR-260615164933', '435276783H', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif'),
(19, 'physique', 'LOMBO LOFUMA Jean', 'LOMBO LOFUMA Jean', NULL, NULL, NULL, NULL, '+243820646942', NULL, 'Taxation spontanée PWA Offline', 'Non précisée', NULL, NULL, 1, '2026-06-15 20:07:37', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif');

-- --------------------------------------------------------

--
-- Structure de la table `contribuables_spontanes`
--

CREATE TABLE `contribuables_spontanes` (
  `id` int(11) NOT NULL,
  `nom` varchar(200) DEFAULT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `plaque` varchar(100) DEFAULT NULL,
  `type_taxe` varchar(100) DEFAULT NULL,
  `article_id` int(11) DEFAULT NULL,
  `gps` text DEFAULT NULL,
  `photo` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `contribuable_documents`
--

CREATE TABLE `contribuable_documents` (
  `id` int(11) NOT NULL,
  `contribuable_id` int(11) NOT NULL,
  `type_document` varchar(100) DEFAULT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `contribuable_qr`
--

CREATE TABLE `contribuable_qr` (
  `id` int(11) NOT NULL,
  `contribuable_id` int(11) DEFAULT NULL,
  `qr_hash` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `corrections_champs_bloques`
--

CREATE TABLE `corrections_champs_bloques` (
  `id` int(11) NOT NULL,
  `nom_champ` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `corrections_champs_bloques`
--

INSERT INTO `corrections_champs_bloques` (`id`, `nom_champ`) VALUES
(1, 'montant_total'),
(2, 'montant_initial'),
(3, 'montant_paye'),
(4, 'montant_converti_cdf'),
(5, 'montant_penalite'),
(6, 'montant_du'),
(7, 'solde_restant');

-- --------------------------------------------------------

--
-- Structure de la table `corrections_documents`
--

CREATE TABLE `corrections_documents` (
  `id` int(11) NOT NULL,
  `type_document` varchar(50) DEFAULT NULL,
  `numero_document` varchar(100) DEFAULT NULL,
  `reference_table` varchar(100) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `raison_modification` text DEFAULT NULL,
  `ancienne_valeur` longtext DEFAULT NULL,
  `nouvelle_valeur` longtext DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `date_modification` datetime DEFAULT current_timestamp(),
  `motif` text DEFAULT NULL,
  `statut` varchar(30) DEFAULT 'en_attente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `corrections_documents`
--

INSERT INTO `corrections_documents` (`id`, `type_document`, `numero_document`, `reference_table`, `reference_id`, `raison_modification`, `ancienne_valeur`, `nouvelle_valeur`, `user_id`, `date_modification`, `motif`, `statut`) VALUES
(1, 'NP', 'NP-BU-CPR-26-000012', 'notes_perception', 1, 'ERREU MATERIEL', '{\"document\":{\"id\":1,\"numero_np\":\"NP-BU-CPR-26-000012\",\"note_debit_id\":1,\"date_ordonnancement\":null,\"date_echeance\":\"2026-06-25\",\"montant_total\":\"0.00\",\"penalite_recouvrement\":\"0.00\",\"banque_id\":null,\"compte_bancaire\":null,\"est_fractionnee\":0,\"statut_fractionnement\":\"aucun\",\"statut\":\"payee\",\"user_ordonnateur_id\":3,\"created_at\":\"2026-06-15 15:28:16\",\"type_np\":\"globale\",\"np_mere_id\":null,\"avis_fractionnement_id\":null,\"numero_tranche\":null,\"declarant_nom\":\"Jeannot\",\"montant_initial\":\"3178000.00\",\"montant_paye\":\"3178000.00\",\"solde_restant\":\"0.00\",\"date_emission\":\"2026-06-15 15:28:16\",\"annotation_autorite\":null,\"sceau_appose\":1,\"penalite_assiette\":\"0.00\"},\"contribuable\":{\"id\":9,\"type_personne\":\"morale\",\"raison_sociale\":\"COURTIER2\",\"nom\":null,\"postnom\":null,\"prenom\":null,\"nif\":\"000012345\",\"rccm\":\"456TYR87UJ\",\"telephone\":\"0820646942\",\"email\":\"nmjpro88@gmail.com\",\"adresse\":\"KINSHASA, MONT-NGAFULA, MBUDI, BANYINDO 03\",\"ville\":\"KINSHASA\",\"territoire\":null,\"province\":null,\"actif\":1,\"created_at\":\"2026-06-02 19:55:07\",\"code_contribuable\":\"CTR-260602195507\",\"id_national\":null,\"telephone_secondaire\":null,\"commune\":null,\"quartier\":null,\"avenue\":null,\"numero_parcelle\":null,\"latitude\":null,\"longitude\":null,\"photo\":null,\"statut\":\"actif\"}}', '{\"document\":{\"id\":1,\"numero_np\":\"NP-BU-CPR-26-000012\",\"note_debit_id\":1,\"date_ordonnancement\":null,\"date_echeance\":\"2026-06-25\",\"montant_total\":\"0.00\",\"penalite_recouvrement\":\"0.00\",\"banque_id\":null,\"compte_bancaire\":\"TMB Compte recettes provinciales (000-TMB-USD-001 \\/ USD)\",\"est_fractionnee\":0,\"statut_fractionnement\":\"aucun\",\"statut\":\"payee\",\"user_ordonnateur_id\":3,\"created_at\":\"2026-06-15 15:28:16\",\"type_np\":\"globale\",\"np_mere_id\":null,\"avis_fractionnement_id\":null,\"numero_tranche\":null,\"declarant_nom\":\"Jeannot\",\"montant_initial\":\"3178000.00\",\"montant_paye\":\"3178000.00\",\"solde_restant\":\"0.00\",\"date_emission\":\"2026-06-15 15:28:16\",\"annotation_autorite\":null,\"sceau_appose\":1,\"penalite_assiette\":\"0.00\"},\"contribuable\":{\"id\":9,\"type_personne\":\"morale\",\"raison_sociale\":\"COURTIER2\",\"nom\":\"MENDE\",\"postnom\":\"BOSMIL\",\"prenom\":\"JOSEPH\",\"nif\":\"000012345\",\"rccm\":\"456TYR87UJ\",\"telephone\":\"0820646942\",\"email\":\"nmjpro88@gmail.com\",\"adresse\":\"KINSHASA, MONT-NGAFULA, MBUDI, BANYINDO 03\",\"ville\":\"KINSHASA\",\"territoire\":null,\"province\":null,\"actif\":1,\"created_at\":\"2026-06-02 19:55:07\",\"code_contribuable\":\"CTR-260602195507\",\"id_national\":null,\"telephone_secondaire\":null,\"commune\":null,\"quartier\":null,\"avenue\":null,\"numero_parcelle\":null,\"latitude\":null,\"longitude\":null,\"photo\":null,\"statut\":\"actif\"}}', 3, '2026-06-15 19:51:42', NULL, 'en_attente'),
(3, 'NP', 'NP-BU-CPR-26-000012', 'notes_perception', 1, 'erreur', '{\"document\":{\"compte_bancaire\":\"TMB Compte recettes provinciales (000-TMB-USD-001 \\/ USD)\",\"banque_id\":null}}', '{\"document\":{\"compte_bancaire\":\"Rawbank Compte recettes provinciales (000-RAW-CDF-001 \\/ CDF)\",\"banque_id\":2}}', 3, '2026-06-29 20:25:51', 'erreur', 'en_attente');

-- --------------------------------------------------------

--
-- Structure de la table `directions`
--

CREATE TABLE `directions` (
  `id` int(11) NOT NULL,
  `nom_direction` varchar(150) NOT NULL,
  `code_direction` varchar(50) NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `visible_taxation` tinyint(1) NOT NULL DEFAULT 1,
  `visible_pwa` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `directions`
--

INSERT INTO `directions` (`id`, `nom_direction`, `code_direction`, `actif`, `visible_taxation`, `visible_pwa`) VALUES
(1, 'DIRECTION IMPOT', 'IMPOT', 1, 1, 0),
(2, 'DIRECTION RECETTES NON FISCALES', 'RNF', 1, 1, 0),
(3, 'DIRECTION ADMINISTRATIVE', 'ADMIN', 1, 1, 0);

-- --------------------------------------------------------

--
-- Structure de la table `document_sequences`
--

CREATE TABLE `document_sequences` (
  `id` int(11) NOT NULL,
  `type_document` varchar(20) NOT NULL,
  `province_id` int(11) NOT NULL,
  `centre_id` int(11) NOT NULL,
  `annee` char(2) NOT NULL,
  `dernier_numero` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `document_sequences`
--

INSERT INTO `document_sequences` (`id`, `type_document`, `province_id`, `centre_id`, `annee`, `dernier_numero`) VALUES
(1, 'CTR', 1, 1, '26', 4),
(20, 'NT', 1, 1, '26', 84),
(21, 'ND', 1, 1, '26', 17),
(22, 'NP', 1, 1, '26', 17),
(23, 'QT', 2, 3, '26', 2),
(24, 'QT', 1, 1, '26', 7),
(25, 'AVF', 1, 1, '26', 3),
(26, 'NT', 2, 3, '26', 3),
(27, 'ND', 2, 3, '26', 6),
(28, 'NP', 2, 3, '26', 2);

-- --------------------------------------------------------

--
-- Structure de la table `document_tokens`
--

CREATE TABLE `document_tokens` (
  `id` int(11) NOT NULL,
  `type_document` varchar(10) NOT NULL,
  `numero_document` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `signature_hash` varchar(255) DEFAULT NULL,
  `montant` decimal(18,2) DEFAULT 0.00,
  `hash_signature` varchar(255) NOT NULL,
  `statut` enum('actif','revoque') DEFAULT 'actif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_revocation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `document_tokens`
--

INSERT INTO `document_tokens` (`id`, `type_document`, `numero_document`, `token`, `signature_hash`, `montant`, `hash_signature`, `statut`, `created_at`, `date_revocation`) VALUES
(29, 'NT', 'NT-BU-CPR-26-000001', '6ae4b585d6711440583d0a8948d8e023e124a73214e71bf7ec590fbbb96efe1b', NULL, 0.00, 'f383983e69a7a21ae2caebcc402f7560a7fe8cc0bdabda94cea48b4a93f66491', 'actif', '2026-06-05 08:46:24', NULL),
(30, 'ND', 'ND-BU-CPR-26-000001', 'afe39b0b2099fb95259e073f0c22bfa487b591771c5b49a4a5db1fef274f7d6b', NULL, 0.00, '0d6d71d2fb9095e9c2ad9d94c7067fcee19be9e7893e41dc8046cf5befde216f', 'actif', '2026-06-05 09:16:50', NULL),
(31, 'NP', 'NP-BU-CPR-26-000001', 'fdfad32ce73b910d97493fa85b38ec27de65247abcfa4c68714d49a21180d6d2', NULL, 0.00, 'be2b7442f531fc05033c0a78ab33be07d3a3c002833bb3e52606f3326d0e0acc', 'actif', '2026-06-05 09:30:35', NULL),
(32, 'NT', 'NT-BU-CPR-26-000002', '91a96cfec7ef1e2a988dd29216643f47', '5cb425d56804c41df3f49d6623c6d07417de0f2ba065fdeb244463b7abff3018', 26600000.00, '', 'actif', '2026-06-05 16:44:23', NULL),
(33, 'NT', 'NT-BU-CPR-26-000003', '708cca4e9db3f48bd3497ce018f38f3d', '1ed61d083c3af81a18f93bc58ce6d54f8af647c8c4f3c85e895a5282c4936bb8', 1820000.00, '', 'actif', '2026-06-12 15:07:25', NULL),
(34, 'ND', 'ND-BU-CPR-26-000002', '28a8710cc28cc239f2fa820d185cb1da', '6f7ff1b875f5d9c3abe0e7a4c12654f8ad50b1097ba29980daa930b9e3436c3a', 1820000.00, '', 'actif', '2026-06-12 15:08:30', NULL),
(35, 'NP', 'NP-BU-CPR-26-000002', '70d5f45efdbb767ab89e5ef743883e30', 'acb49e0ba020ba38998749b13a185f6af03852a4e82e6d1f55ea32cd3c461f59', 1820000.00, '', 'actif', '2026-06-12 15:10:22', NULL),
(36, 'QUITTANCE', 'QT-BU-CPR-26-000002', 'e21d403be7d8a699d107e753592b4ac0', '5c01730c483295268901801951b0e82b7bf2cafc5bc949637f878ce2b97faea5', 1820000.00, '', 'actif', '2026-06-12 16:40:39', NULL),
(37, 'QT', 'QT-BU-CPR-26-000002', '28c3fe067d984191d5071386f953da2f', '9730a5f6e40575a2a816c9186c1cb4e793bc30691b3070c60609d1761540ed08', 1820000.00, '', 'actif', '2026-06-12 16:49:38', NULL),
(38, 'QUITTANCE', 'QT-BU-CPR-26-000003', '758f3c8106996325c6407cfb7437ddd0', '245c6a362707337fcb9aae619efa3b066a764ff6ae71c353af0301d2d24bdb1d', 26600000.00, '', 'actif', '2026-06-12 17:01:46', NULL),
(39, 'QT', 'QT-BU-CPR-26-000003', '6179c1938d76df4c28788cb875bb0318', 'a3de49c16a59a2e509d1e8d8eaba31d20f7fa1b186ebd4f6144b53b6ce6aa948', 26600000.00, '', 'actif', '2026-06-12 17:02:01', NULL),
(40, 'QUITTANCE', 'QT-BU-CPR-26-000004', '34c386d250e48805be707b9c5fa438b0', '6eb1675e8876a07877d8dd4ff7d1613d51af6bf888b731a79ed83d703ae8e13d', 30000000.00, '', 'actif', '2026-06-12 17:58:12', NULL),
(41, 'NPF', 'NP-BU-CPR-26-000006-002', 'f15ecfb594c54bf288677b28b1a606fd', '4debad6abaa71537151e3a8a0bae87ecb3223d6131c638559cf6fbdf374dca1b', 10000000.00, '', 'actif', '2026-06-12 18:30:40', NULL),
(42, 'ATTESTATIO', 'NP-BU-CPR-26-000006-001', '8db6606980c4395f8fdee7ed3fdc4291', '15db6fd0a10a862ebae6a4854bf6e72e955c5aa9ed6dd59f891750379c475586', 25000000.00, '', 'actif', '2026-06-12 19:00:26', NULL),
(43, 'ATTESTATIO', 'NP-BU-CPR-26-000006-002', 'f65fc2eb37bab21c782b6160370b76e1', '68849dd1d24b3246d85ffbb8634bb5c1c3ad487f91a3743f0089c167c70d0172', 10000000.00, '', 'actif', '2026-06-12 19:02:24', NULL),
(44, 'ATTESTATIO', 'NP-BU-CPR-26-000006-002', '2636098e8673979c2e79228103f366af', 'b3bc784c765aa0da7673c692a32a862a9308d3f6226d86747e7601273c8db3a5', 10000000.00, '', 'actif', '2026-06-12 19:05:28', NULL),
(45, 'QUITTANCE', 'QT-BU-CPR-26-000005', '55da53d7b45a42ce78e72472b2de2717', '02e5aa5c97617283f52d9e8a304205e9aba3a0fd50eee11920e26f416236fe63', 35000000.00, '', 'actif', '2026-06-12 19:17:16', NULL),
(46, 'QT', 'QT-BU-CPR-26-000005', '9f8999e64f8ff9fb9bf8ad91fbcd9418', 'b8ad56362d4315e42f6cedea313fb58cec9cb6e10269af5307f58b15a293c62b', 35000000.00, '', 'actif', '2026-06-12 19:17:20', NULL),
(47, 'NT', 'NT-BU-CPR-26-000009', '2d1dbb1db25e51f5718b7b71f46ca257', '68681291d7eb3b7a467f7c226c7c1d3bb9a4564d2a8f392c032205d220b7328f', 52500000.00, '', 'actif', '2026-06-13 12:28:34', NULL),
(48, 'NP', 'NP-BU-CPR-26-000007', '10b6f71a7d7a6d5ad19c0e489f8a25d8', '02b69aad047a3d05090e3a7e720baab4fcce37eafa7d7f0b8b041de50894e6ae', 52500000.00, '', 'actif', '2026-06-13 12:30:20', NULL),
(49, 'AMR', 'NP-BU-CPR-26-000008', '166d1b8df7edc8827088964ebd2ec545', '3b91a363647d630f2384350cdf282936fddb65d91ca235b395a5752cc9a191a1', 5775000.00, '', 'actif', '2026-06-13 14:20:35', NULL),
(50, 'NT', 'NT-BU-CPR-26-000018', 'fcf8f3aae011e2cc73b0a9eea4ce5ea7', '41f579c575a05e40817dc33a683bf9de2fecfc2afc023f6df7316e918601c531', 806400.00, '', 'actif', '2026-06-14 11:37:10', NULL),
(51, 'NT', 'NT-BU-CPR-26-000019', 'dcf762ed33f4f9e9aac7cdb11588a689', 'a4223343446fad430f9f9a64d254e6c1122069d5faf5a769ddb0cde26cabd961', 4277448.00, '', 'actif', '2026-06-14 11:44:02', NULL),
(52, 'NT', 'NT-BU-CPR-26-000025', 'ea5dcab5c14dc24473c82392b6d7dcfb', 'e4464b06c21db3d0fb7a85457fb232b258411a300a2dd299b9c2aabac7b78df4', 405000.00, '', 'actif', '2026-06-14 14:34:41', NULL),
(53, 'NP', 'NP-BU-CPR-26-000009', '1d5f24b7f2c8d1db3e72cdbc87d39f62', '0c12bf38a4afe77d15ad538c8d37bfa5110ec052393b4d735c5692fc7897a09f', 805000.00, '', 'actif', '2026-06-14 14:42:33', NULL),
(54, 'NT', 'NT-BU-CPR-26-000026', '8dac85cca78271496b75eb38e13942b0', '7db49992566ca4f4bc8c53a3636981661b7128345083b57a79da2faa6e2cfcd6', 882000.00, '', 'actif', '2026-06-14 15:16:16', NULL),
(55, 'NT', 'NT-BU-CPR-26-000027', '52c9658e2549728d078f803ced203a6c', '351c3dd411b6a3424f7f15808239a601added43df94eb10fdce7f279cc2b161b', 351000.00, '', 'actif', '2026-06-14 15:50:41', NULL),
(56, 'NP', 'NP-BU-CPR-26-000010', '9c0edcfed3ec32e086d6dfd344cda8ff', '3c090533e67d4c5c214ff93368682d80647c5989aa8e5a9a2010366b3cafe3aa', 351000.00, '', 'actif', '2026-06-14 15:52:02', NULL),
(57, 'NP', 'NP-BU-CPR-26-000011', 'c1948fa83ff1845b79b4bb3f039eaeda', 'ba2b7d0cfc46db5bd15ae332e8e2fc5a91af9bf4b706f844fcdcd2a41162a363', 960000.00, '', 'actif', '2026-06-14 15:55:36', NULL),
(58, 'NT', 'NT-BU-CPR-26-000035', '840dc36b554b8e41546bc501fa41be9d', 'ae57da9e1318080fc28c6afce0999ef71fc0b68e908192431b657524643c094a', 1296000.00, '', 'actif', '2026-06-14 18:33:03', NULL),
(59, 'NT', 'NT-BU-CPR-26-000036', '28f1bfb150b63acca5000eae9999801c', 'ae23a678e639f395c6eae1c7ba34daba7d5f831b761aeb84dc1fd73c0072f1c9', 1080000.00, '', 'actif', '2026-06-14 18:49:40', NULL),
(60, 'NT', 'NT-BU-CPR-26-000050', 'd0eb00a02d0aeb5304d3968613aaf1e3', '6ecb98fddeb7b5f5792bb5f0664d798f377276f11737875d7c6c5d224a20bd77', 266000000.00, '', 'actif', '2026-06-15 11:04:52', NULL),
(61, 'NT', 'NT-BU-CPR-26-000051', '3ccef44c5d2b827b9d769fac8330228e', 'ac26b59af32c6b3e7c2c1d87cb35f4d02c78f5e88f815cde095c7746855233bb', 0.00, '', 'actif', '2026-06-15 12:11:39', NULL),
(62, 'NT', 'NT-BU-CPR-26-000053', 'be593ed05098246411b6dcf2e9223fb2', '7b50bf0de19a747e47ab214da4e092b1f7b67eba05adb9a7dab5c443b11f7242', 3178000.00, '', 'actif', '2026-06-15 12:56:09', NULL),
(63, 'NT', 'NT-BU-CPR-26-000055', '278c51b175b3b428fbe83f1c2655da52', '44794e0331451acbf270bd16fd7ff6ef57c6f7e4f1b78a378267e346d2dd5b37', 3178000.00, '', 'actif', '2026-06-15 13:15:02', NULL),
(64, 'ND', 'ND-BU-CPR-26-000012', '7080bb13ee37d61eddb3155158627f6b', '899ad72a1fa22233dec727766d399fb93c42fa7cc78fdfb9c8f319338c0bf126', 3178000.00, '', 'actif', '2026-06-15 13:27:21', NULL),
(65, 'NP', 'NP-BU-CPR-26-000012', '3dd66405e33507d0b8ed91955038c206', '7f74dd2dfe9fff811cc879e13aa28a4c076f89aa612db5ce718638d596e8d86d', 3178000.00, '', 'actif', '2026-06-15 13:28:23', NULL),
(66, 'NP', 'NP-BU-CPR-26-000013', 'f4b3e6634dbaf83f07f9723f147b8d88', '827f160d2d04f1fe2433286c9dd5a58bb32b11c123c9e8d81d62c2996eadcac4', 0.00, '', 'actif', '2026-06-15 14:01:33', NULL),
(67, 'ATTESTATIO', 'NP-BU-CPR-26-000012', 'f2b122107236cc187f580cd374e35a27', '831796a8df49311d2993a57c1105b712dddc6a6cbc201bd77bff5736092c9bea', 3178000.00, '', 'actif', '2026-06-15 14:40:40', NULL),
(68, 'ATTESTATIO', 'NP-BU-CPR-26-000012', 'c7e22dc418b057256ee7dd2c3369fd94', 'e39ce1a440cfafd1d96b20bf1b76a6273844dc3c0d35aefa240140d354e24dd9', 3178000.00, '', 'actif', '2026-06-15 14:47:38', NULL),
(69, 'NT', 'NT-BU-CPR-26-000056', '7d90eaf0e59d8a9822f694164101d48d', 'b41a91c2dc77f9c1a106be5f2e1f39eb481cf20c5fdb8c47c10a6b772417dbd9', 21258000.00, '', 'actif', '2026-06-15 14:50:30', NULL),
(70, 'NP', 'NP-BU-CPR-26-000014', '9ff915ecd6cfe1fc98dd3b2a99531abf', 'ee2af17450e1b6e2722955915bb16e4f9950f4fbefebcdf3e7efde521d2b687e', 21258000.00, '', 'actif', '2026-06-15 14:53:08', NULL),
(71, 'AMR', 'NP-BU-CPR-26-000014', '811e186a1c1a87fb7a169fa6b319d845', 'e9f42b7c149181b3f834a4dde229bf76c98d0f9c851b6ec1ed09698e4e8fb80e', 23383800.00, '', 'actif', '2026-06-15 14:56:36', NULL),
(72, 'QUITTANCE', 'QT-BU-CPR-26-000006', '04ad0644eae401343faeae942198c5c4', '528dd8db4bc6adeb6a8361440ad2afc262bb4aead530bf3fd3071bc802aa1ee9', 21258000.00, '', 'actif', '2026-06-15 15:14:25', NULL),
(73, 'QT', 'QT-BU-CPR-26-000006', 'e40a2dfdf3941bcac57c9f151efc2c77', 'e41f246699d83ffc07347ac8fe1cc006a2f952eb8472b0ffb8dc6f5b0919bac0', 21258000.00, '', 'actif', '2026-06-15 15:14:28', NULL),
(74, 'NT', 'NT-BU-CPR-26-000073', '4bffc786e55c3946eac564a730c63f42', 'afd7e61fced1c7b36b727c8f1f1ee0a47e64f03bdd8f4ae130892af20afe3291', 720000.00, '', 'actif', '2026-06-26 17:34:31', NULL),
(75, 'ND', 'ND-BU-CPR-26-000015', 'f1560932388702b4fc78997405be019d', '99187548566b927627fa8737c747d6c2d303b79b171cd40a72cefafb43ffe67e', 720000.00, '', 'actif', '2026-06-26 17:44:39', NULL),
(76, 'NP', 'NP-BU-CPR-26-000015', 'ded55d34ae3eab1d48d9f7549f368b79', 'a8cff8e71219d1cdb7a985ad4fd93729621c1f3d86b4db675090a66c020b8149', 720000.00, '', 'actif', '2026-06-26 17:45:57', NULL),
(77, 'ATTESTATIO', 'NP-BU-CPR-26-000015-001', 'b3ff399f058ac7c7058e4fb92c85b654', '38f556c4f499b69d6a07d49057912b9902f95afd4d8be2e70c8f733a922e6bdc', 500000.00, '', 'actif', '2026-06-26 17:48:29', NULL),
(78, 'NPF', 'NP-BU-CPR-26-000015-001', '40082d6c511faf0caaa7db5153b1c657', 'f769690ec179e94e655ba93a18657f2cf791cbe9ee18a90f230d69500a58f1f3', 0.00, '', 'actif', '2026-06-26 17:52:01', NULL),
(79, 'QUITTANCE', 'QT-BU-CPR-26-000007', '6a1b3156dc84bfe726e2b8ba9f7a1274', '1554d11a5e687da7e700d1626ca4317603b9b799f9fa8c5409430437f9b10fd3', 720000.00, '', 'actif', '2026-06-26 21:29:58', NULL),
(80, 'QT', 'QT-BU-CPR-26-000007', '5764f83fd59ad920aa00abe99dde8a9b', '534641054e080070af68411bcc8ab4d546ad113101b1ca62fd7f08ffbdc34001', 720000.00, '', 'actif', '2026-06-26 21:30:07', NULL),
(81, 'NT', 'NT-BU-CPR-26-000082', 'd3c6684e49a89e8e4393978d0be53bf2', 'b5bbfae176549af00b6dd2430deee7de7753f3744c211abc4138173ed855b868', 5400000.00, '', 'actif', '2026-06-26 23:12:56', NULL),
(82, 'NT', 'NT-BU-CPR-26-000083', '8cbf04ac142bcb57cebb81f061f84872', '26b0b165b9d55934142497b65da446d448bed4f6027917d22a09028c9e551916', 5325600.00, '', 'actif', '2026-06-27 00:16:49', NULL),
(83, 'NP', 'NP-BU-CPR-26-000016', '94fd455b07619e374c82803b8fb22e70', '6a78dce91eb3191a757fba17acee623e9ea0454917f107e973a60f1fbd4a8c77', 5325600.00, '', 'actif', '2026-06-27 00:20:53', NULL),
(84, 'AMR', 'NP-BU-CPR-26-000016', 'dc219d691331b14fd424d45a0baf696c', '44f0a2dbdee6675cd1dd17c13757d5e034fa1bc188ddef133277944d8868ae4b', 5858160.00, '', 'actif', '2026-06-27 00:26:22', NULL),
(85, 'ND', 'ND-TS-KIS-26-000001', '4e26167d6f2fd8edc43d9153419e67fe', 'd12ffb6c6cfc4c7c56cf99ba56ef8a47183bea85bc93592cd590cecb71be713a', 5400000.00, '', 'actif', '2026-06-27 14:22:49', NULL),
(86, 'ND', 'ND-TS-KIS-26-000003', '694f9c119eb25980d6a86a95ac561fdc', 'cf5aa25982b5c7c1c99bc952ad9708a9021b101a82b7bc45c2e8a1cf039dbd74', 3640000.00, '', 'actif', '2026-06-27 14:26:00', NULL),
(87, 'NP', 'NP-TS-KIS-26-000001', '85ba5fce0dab0f084929a281e5a914e8', '5fe26722cfd358a648c579524dd9bd84291ab3bd9ac8a8666c55701dddb919d7', 386400.00, '', 'actif', '2026-06-27 16:04:16', NULL),
(88, 'ATTESTATIO', 'NP-TS-KIS-26-000001', '5160ea8c77469ac417c5f1fc24462b7a', 'aea66a39d6da2baca92731d08e13f51c81035a43f09480faa0e376e060e481b4', 386400.00, '', 'actif', '2026-06-27 16:21:08', NULL),
(89, 'ATTESTATIO', 'NP-TS-KIS-26-000001', 'c7dbace53d2c5ab5d0930f07d49bf2cd', '436bbad5b0cd6f3eba75507833de129a9215147db6ac682a3e62e4130a488eb8', 386400.00, '', 'actif', '2026-06-27 16:21:39', NULL),
(90, 'NPF', 'NP-BU-CPR-26-000015-002', '463d2c4270bcb20a7403b599b5f05014', '73d0a919ca095e7eb87e2895c96a36c2157267977d496c7594bd1564707fc7d5', 0.00, '', 'actif', '2026-06-27 17:40:36', NULL),
(91, 'QUITTANCE', 'QT-TS-KIS-26-000002', 'a6a3935b436f55e4938b34e5accb9f17', '1364532ae7b27d6c875d95230029a2e1b646844b708f9ec7de2f46605128bffd', 26700027.00, '', 'actif', '2026-06-27 18:26:25', NULL),
(92, 'QT', 'QT-TS-KIS-26-000002', 'b1bcf4a9064f78bede3bc5208f228e91', '2ae5a5a4f1507e34235fedccbf62e1527a1287f1de23c74b4be06a1de90d8d55', 26700027.00, '', 'actif', '2026-06-27 18:26:29', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `historique_taux`
--

CREATE TABLE `historique_taux` (
  `id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `province_id` int(11) NOT NULL,
  `ancien_taux` decimal(18,2) DEFAULT NULL,
  `nouveau_taux` decimal(18,2) DEFAULT NULL,
  `date_modification` datetime DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `impressions_documents`
--

CREATE TABLE `impressions_documents` (
  `id` int(11) NOT NULL,
  `type_document` varchar(30) NOT NULL,
  `numero_document` varchar(150) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `impressions_documents`
--

INSERT INTO `impressions_documents` (`id`, `type_document`, `numero_document`, `user_id`, `ip_address`, `created_at`) VALUES
(16, 'ND', 'ND-BU-CPR-26-000001', 3, '::1', '2026-06-05 09:16:50'),
(17, 'ND', 'ND-BU-CPR-26-000001', 3, '::1', '2026-06-05 09:20:41'),
(18, 'NP', 'NP-BU-CPR-26-000001', 3, '::1', '2026-06-05 09:30:35'),
(19, 'NP', 'NP-BU-CPR-26-000001', 3, '::1', '2026-06-05 09:34:46'),
(20, 'NP', 'NP-BU-CPR-26-000001', 3, '::1', '2026-06-05 09:48:56'),
(21, 'NP', 'NP-BU-CPR-26-000001', 3, '::1', '2026-06-05 09:49:05'),
(22, 'ND', 'ND-BU-CPR-26-000001', 3, '::1', '2026-06-05 09:49:08'),
(23, 'NP', 'NP-BU-CPR-26-000001', 3, '::1', '2026-06-05 09:53:50'),
(24, 'NP', 'NP-BU-CPR-26-000001', 3, '::1', '2026-06-05 09:53:54'),
(25, 'ND', 'ND-BU-CPR-26-000001', 3, '::1', '2026-06-05 09:53:57'),
(26, 'NP', 'NP-BU-CPR-26-000001', 3, '::1', '2026-06-05 10:10:17'),
(27, 'NP', 'NP-BU-CPR-26-000001', 3, '::1', '2026-06-05 10:10:21'),
(28, 'NP', 'NP-BU-CPR-26-000001', 3, '::1', '2026-06-05 10:19:19'),
(29, 'NP', 'NP-BU-CPR-26-000001', 3, '::1', '2026-06-05 10:22:53'),
(30, 'ND', 'ND-BU-CPR-26-000001', 3, '::1', '2026-06-05 10:23:00'),
(31, 'NP', 'NP-BU-CPR-26-000001', 3, '::1', '2026-06-05 10:48:12'),
(32, 'ND', 'ND-BU-CPR-26-000001', 3, '::1', '2026-06-05 10:48:49'),
(33, 'ND', 'ND-BU-CPR-26-000001', 3, '::1', '2026-06-05 10:54:58'),
(34, 'NP', 'NP-BU-CPR-26-000001', 3, '::1', '2026-06-05 10:56:42'),
(35, 'ND', 'ND-BU-CPR-26-000001', 3, '::1', '2026-06-05 12:23:23'),
(36, 'ND', 'ND-BU-CPR-26-000002', 3, '::1', '2026-06-12 15:08:29'),
(37, 'NP', 'NP-BU-CPR-26-000002', 3, '::1', '2026-06-12 15:10:21'),
(38, 'NP', 'NP-BU-CPR-26-000002', 3, '::1', '2026-06-12 15:18:54'),
(39, 'NP', 'NP-BU-CPR-26-000002', 3, '::1', '2026-06-12 15:20:26'),
(40, 'NP', 'NP-BU-CPR-26-000002', 3, '::1', '2026-06-12 15:52:13'),
(41, 'QUITTANCE', 'QT-BU-CPR-26-000002', 3, '::1', '2026-06-12 16:49:38'),
(42, 'QUITTANCE', 'QT-BU-CPR-26-000003', 3, '::1', '2026-06-12 17:02:01'),
(43, 'NPF', 'NP-BU-CPR-26-000005-001', 3, '::1', '2026-06-12 17:14:44'),
(44, 'NPF', 'NP-BU-CPR-26-000006-002', 3, '::1', '2026-06-12 18:22:20'),
(45, 'NPF', 'NP-BU-CPR-26-000006-002', 3, '::1', '2026-06-12 18:30:40'),
(46, 'NPF', 'NP-BU-CPR-26-000006-002', 3, '::1', '2026-06-12 18:45:49'),
(47, 'QUITTANCE', 'QT-BU-CPR-26-000005', 3, '::1', '2026-06-12 19:17:20'),
(48, 'QUITTANCE', 'QT-BU-CPR-26-000005', 3, '::1', '2026-06-12 19:18:46'),
(49, 'QUITTANCE', 'QT-BU-CPR-26-000005', 3, '::1', '2026-06-12 19:19:29'),
(50, 'NP', 'NP-BU-CPR-26-000007', 3, '::1', '2026-06-13 12:30:20'),
(51, 'NP', 'NP-BU-CPR-26-000009', 3, '::1', '2026-06-14 14:42:32'),
(52, 'QUITTANCE', 'QT-BU-CPR-26-000006', 3, '::1', '2026-06-15 15:14:28');

-- --------------------------------------------------------

--
-- Structure de la table `modes_paiement`
--

CREATE TABLE `modes_paiement` (
  `id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `actif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `modes_paiement`
--

INSERT INTO `modes_paiement` (`id`, `code`, `libelle`, `actif`) VALUES
(1, 'MOMO', 'Mobile Money', 1),
(2, 'CARTE', 'Carte Bancaire', 1),
(3, 'VIREMENT', 'Virement Bancaire', 1),
(4, 'CAISSE', 'Versement à la Caisse', 1);

-- --------------------------------------------------------

--
-- Structure de la table `notes_debit`
--

CREATE TABLE `notes_debit` (
  `id` int(11) NOT NULL,
  `numero_nd` varchar(80) NOT NULL,
  `note_taxation_id` int(11) DEFAULT NULL,
  `date_liquidation` date DEFAULT NULL,
  `total_exigible` decimal(18,2) DEFAULT 0.00,
  `penalite_assiette` decimal(18,2) DEFAULT 0.00,
  `observation` text DEFAULT NULL,
  `statut` enum('en_controle','validee','rejete') DEFAULT 'en_controle',
  `ordonnancement_effectue` tinyint(1) DEFAULT 0,
  `user_liquidateur_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `decision` enum('conforme','rejetee','corriger') DEFAULT 'conforme',
  `date_validation` datetime DEFAULT NULL,
  `user_validateur_id` int(11) DEFAULT NULL,
  `montant_acte` decimal(18,2) DEFAULT 0.00,
  `montant_frais_admin` decimal(18,2) DEFAULT 0.00,
  `montant_frais_tech` decimal(18,2) DEFAULT 0.00,
  `montant_total` decimal(18,2) DEFAULT 0.00,
  `penalite_recouvrement` decimal(18,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notes_debit`
--

INSERT INTO `notes_debit` (`id`, `numero_nd`, `note_taxation_id`, `date_liquidation`, `total_exigible`, `penalite_assiette`, `observation`, `statut`, `ordonnancement_effectue`, `user_liquidateur_id`, `created_at`, `decision`, `date_validation`, `user_validateur_id`, `montant_acte`, `montant_frais_admin`, `montant_frais_tech`, `montant_total`, `penalite_recouvrement`) VALUES
(1, 'ND-BU-CPR-26-000012', 18, '2026-06-15', 3178000.00, 0.00, 'DOSSIER VALIDE', 'validee', 1, 3, '2026-06-15 13:26:46', 'conforme', '2026-06-15 15:26:54', 3, 3150000.00, 14000.00, 14000.00, 3178000.00, 0.00),
(2, 'ND-BU-CPR-26-000013', 13, '2026-06-15', 266000000.00, 0.00, 'DOSSIER VALIDE', 'validee', 1, 3, '2026-06-15 13:59:55', 'conforme', '2026-06-15 16:00:06', 3, 266000000.00, 0.00, 0.00, 266000000.00, 0.00),
(3, 'ND-BU-CPR-26-000014', 19, '2026-06-15', 21258000.00, 230000.00, '', 'validee', 1, 3, '2026-06-15 14:51:21', 'conforme', '2026-06-15 16:51:27', 3, 21000000.00, 14000.00, 14000.00, 21258000.00, 0.00),
(4, 'ND-BU-CPR-26-000015', 49, '2026-06-26', 720000.00, 0.00, 'DOSSIER VALIDE', 'validee', 1, 3, '2026-06-26 17:44:19', 'conforme', '2026-06-26 19:44:27', 3, 720000.00, 0.00, 0.00, 720000.00, 0.00),
(5, 'ND-BU-CPR-26-000016', 59, '2026-06-27', 5325600.00, 0.00, 'ras', 'validee', 1, 3, '2026-06-27 00:19:53', 'conforme', '2026-06-27 02:20:04', 3, 5320000.00, 2800.00, 2800.00, 5325600.00, 0.00),
(6, 'ND-TS-KIS-26-000001', 58, '2026-06-27', 5400000.00, 0.00, 'DOSSIER VALIDE', 'validee', 0, 9, '2026-06-27 14:22:34', 'conforme', '2026-06-27 17:45:02', 10, 5400000.00, 0.00, 0.00, 5400000.00, 0.00),
(7, 'ND-TS-KIS-26-000002', 32, '2026-06-27', 5320000.00, 0.00, 'dossier', 'validee', 0, 9, '2026-06-27 14:24:47', 'conforme', '2026-06-27 16:24:50', 9, 5320000.00, 0.00, 0.00, 5320000.00, 0.00),
(8, 'ND-TS-KIS-26-000003', 31, '2026-06-27', 3640000.00, 0.00, 'DOSSIER VALIDE', 'validee', 0, 9, '2026-06-27 14:25:38', 'conforme', '2026-06-27 17:43:32', 10, 3640000.00, 0.00, 0.00, 3640000.00, 0.00),
(9, 'ND-TS-KIS-26-000004', 30, '2026-06-27', 386400.00, 0.00, '', 'validee', 1, 9, '2026-06-27 14:29:06', 'conforme', '2026-06-27 16:29:11', 9, 386400.00, 0.00, 0.00, 386400.00, 0.00),
(10, 'ND-BU-CPR-26-000017', 63, '2026-06-27', 26700027.00, 0.00, '', 'validee', 1, 3, '2026-06-27 17:45:37', 'conforme', '2026-06-27 19:45:47', 3, 26700027.00, 0.00, 0.00, 26700027.00, 0.00),
(11, 'ND-TS-KIS-26-000005', 29, '2026-06-29', 3444000000.00, 0.00, '', 'validee', 0, 9, '2026-06-29 16:27:52', 'conforme', '2026-06-29 18:28:00', 9, 3444000000.00, 0.00, 0.00, 3444000000.00, 0.00),
(12, 'ND-TS-KIS-26-000006', 28, '2026-06-29', 378112000.00, 0.00, '', 'validee', 1, 9, '2026-06-29 16:29:40', 'conforme', '2026-06-29 18:30:20', 10, 378112000.00, 0.00, 0.00, 378112000.00, 0.00);

-- --------------------------------------------------------

--
-- Structure de la table `notes_perception`
--

CREATE TABLE `notes_perception` (
  `id` int(11) NOT NULL,
  `numero_np` varchar(80) NOT NULL,
  `note_debit_id` int(11) NOT NULL,
  `date_ordonnancement` date DEFAULT NULL,
  `date_echeance` date DEFAULT NULL,
  `montant_total` decimal(18,2) DEFAULT 0.00,
  `penalite_recouvrement` decimal(18,2) DEFAULT 0.00,
  `banque_id` int(11) DEFAULT NULL,
  `compte_bancaire` varchar(150) DEFAULT NULL,
  `est_fractionnee` tinyint(1) DEFAULT 0,
  `statut_fractionnement` enum('aucun','en_cours','fractionnee') DEFAULT 'aucun',
  `statut` enum('en_attente','non_payee','partiellement_payee','payee','defaillante','annulee') DEFAULT 'en_attente',
  `user_ordonnateur_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type_np` enum('globale','fractionnee') DEFAULT 'globale',
  `np_mere_id` int(11) DEFAULT NULL,
  `avis_fractionnement_id` int(11) DEFAULT NULL,
  `numero_tranche` int(11) DEFAULT NULL,
  `declarant_nom` varchar(150) DEFAULT NULL,
  `montant_initial` decimal(18,2) DEFAULT 0.00,
  `montant_paye` decimal(18,2) DEFAULT 0.00,
  `solde_restant` decimal(18,2) DEFAULT 0.00,
  `date_emission` datetime DEFAULT NULL,
  `annotation_autorite` text DEFAULT NULL,
  `sceau_appose` tinyint(1) DEFAULT 0,
  `penalite_assiette` decimal(18,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notes_perception`
--

INSERT INTO `notes_perception` (`id`, `numero_np`, `note_debit_id`, `date_ordonnancement`, `date_echeance`, `montant_total`, `penalite_recouvrement`, `banque_id`, `compte_bancaire`, `est_fractionnee`, `statut_fractionnement`, `statut`, `user_ordonnateur_id`, `created_at`, `type_np`, `np_mere_id`, `avis_fractionnement_id`, `numero_tranche`, `declarant_nom`, `montant_initial`, `montant_paye`, `solde_restant`, `date_emission`, `annotation_autorite`, `sceau_appose`, `penalite_assiette`) VALUES
(1, 'NP-BU-CPR-26-000012', 1, NULL, '2026-06-25', 0.00, 0.00, 2, 'Rawbank Compte recettes provinciales (000-RAW-CDF-001 / CDF)', 0, 'aucun', 'payee', 3, '2026-06-15 13:28:16', 'globale', NULL, NULL, NULL, 'Jeannot', 3178000.00, 3178000.00, 0.00, '2026-06-15 15:28:16', NULL, 1, 0.00),
(2, 'NP-BU-CPR-26-000013', 2, NULL, '2026-06-25', 0.00, 0.00, NULL, NULL, 0, 'aucun', 'payee', 3, '2026-06-15 14:00:24', 'globale', NULL, NULL, NULL, 'Jeannot', 266000000.00, 266000000.00, 0.00, '2026-06-15 16:00:24', NULL, 1, 0.00),
(3, 'NP-BU-CPR-26-000014', 3, NULL, '2026-05-23', 0.00, 0.00, NULL, NULL, 0, 'aucun', 'payee', 3, '2026-05-15 14:51:47', 'globale', NULL, NULL, NULL, 'Jeannot', 21258000.00, 21258000.00, 0.00, '2026-05-15 16:51:47', NULL, 1, 230000.00),
(4, 'NP-BU-CPR-26-000015', 4, NULL, '2026-07-08', 0.00, 0.00, NULL, NULL, 0, 'aucun', 'payee', 3, '2026-06-26 17:45:46', 'globale', NULL, NULL, NULL, 'Jeannot', 720000.00, 720000.00, 0.00, '2026-06-26 19:45:46', 'ACCORDER', 1, 0.00),
(5, 'NP-BU-CPR-26-000015-001', 4, NULL, '2026-06-26', 0.00, 0.00, NULL, NULL, 0, 'aucun', 'payee', 3, '2026-06-26 17:47:16', 'fractionnee', 4, 1, 1, 'Kembo', 500000.00, 500000.00, 0.00, '2026-06-26 19:47:16', 'ACCORDER', 1, 0.00),
(6, 'NP-BU-CPR-26-000015-002', 4, NULL, '2026-07-08', 0.00, 0.00, NULL, NULL, 0, 'aucun', 'payee', 3, '2026-06-26 17:47:17', 'fractionnee', 4, 1, 2, 'Kembo', 220000.00, 220000.00, 0.00, '2026-06-26 19:47:17', 'ACCORDER', 1, 0.00),
(7, 'NP-BU-CPR-26-000016', 5, NULL, '2026-06-05', 0.00, 0.00, NULL, NULL, 0, 'aucun', 'defaillante', 3, '2026-05-27 00:20:46', 'globale', NULL, NULL, NULL, 'Jeannot', 5858160.00, 0.00, 5858160.00, '2026-05-27 02:20:46', NULL, 1, 0.00),
(8, 'NP-TS-KIS-26-000001', 9, NULL, '2026-07-08', 0.00, 0.00, NULL, NULL, 0, 'aucun', 'payee', 7, '2026-06-27 16:04:04', 'globale', NULL, NULL, NULL, 'Kembo', 386400.00, 386400.00, 0.00, '2026-06-27 18:04:04', NULL, 1, 0.00),
(9, 'NP-BU-CPR-26-000017', 10, NULL, '2026-07-08', 0.00, 0.00, NULL, NULL, 0, 'aucun', 'payee', 3, '2026-06-27 17:46:20', 'globale', NULL, NULL, NULL, 'Jeannot', 26700027.00, 26700027.00, 0.00, '2026-06-27 19:46:20', NULL, 1, 0.00),
(10, 'NP-TS-KIS-26-000002', 12, NULL, '2026-07-09', 0.00, 0.00, NULL, NULL, 0, 'aucun', 'en_attente', 7, '2026-06-29 16:31:47', 'globale', NULL, NULL, NULL, 'Kembo', 378112000.00, 0.00, 378112000.00, '2026-06-29 18:31:47', NULL, 1, 0.00);

--
-- Déclencheurs `notes_perception`
--
DELIMITER $$
CREATE TRIGGER `mark_nd_ordonnancee` AFTER INSERT ON `notes_perception` FOR EACH ROW BEGIN
    UPDATE notes_debit
    SET ordonnancement_effectue = TRUE
    WHERE id = NEW.note_debit_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `notes_perception_fractions`
--

CREATE TABLE `notes_perception_fractions` (
  `id` int(11) NOT NULL,
  `numero_fraction` varchar(100) NOT NULL,
  `avis_id` int(11) NOT NULL,
  `note_mere_id` int(11) NOT NULL,
  `montant_fraction` decimal(18,2) NOT NULL,
  `date_echeance` date NOT NULL,
  `statut` enum('en_attente','partiellement_payee','payee','en_retard','annulee') DEFAULT 'en_attente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notes_taxation`
--

CREATE TABLE `notes_taxation` (
  `id` int(11) NOT NULL,
  `numero_nt` varchar(80) NOT NULL,
  `contribuable_id` int(11) NOT NULL,
  `centre_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `exercice` int(11) NOT NULL,
  `statut` enum('brouillon','en_attente_liquidation','liquidee','rejetee','annulee') DEFAULT 'brouillon',
  `total_estime` decimal(18,2) DEFAULT 0.00,
  `devise` varchar(10) DEFAULT 'CDF',
  `taux_change` decimal(18,4) DEFAULT 1.0000,
  `user_taxateur_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `montant_acte_total` decimal(18,2) DEFAULT 0.00,
  `montant_frais_admin_total` decimal(18,2) DEFAULT 0.00,
  `montant_frais_tech_total` decimal(18,2) DEFAULT 0.00,
  `penalite_assiette` decimal(18,2) DEFAULT 0.00,
  `penalite_recouvrement` decimal(18,2) DEFAULT 0.00,
  `source_creation` enum('WEB','PWA_OFFLINE','MOBILE','API') DEFAULT 'WEB'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notes_taxation`
--

INSERT INTO `notes_taxation` (`id`, `numero_nt`, `contribuable_id`, `centre_id`, `service_id`, `exercice`, `statut`, `total_estime`, `devise`, `taux_change`, `user_taxateur_id`, `created_at`, `montant_acte_total`, `montant_frais_admin_total`, `montant_frais_tech_total`, `penalite_assiette`, `penalite_recouvrement`, `source_creation`) VALUES
(1, 'NT-BU-CPR-26-000038', 11, 1, 2, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-14 23:09:12', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(2, 'NT-BU-CPR-26-000039', 11, 1, 2, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-14 23:12:13', 1260000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(3, 'NT-BU-CPR-26-000040', 10, 1, 2, 2026, 'brouillon', 1008000.00, 'CDF', 1.0000, 3, '2026-06-14 23:33:24', 1008000.00, 0.00, 0.00, 200000.00, 0.00, 'WEB'),
(4, 'NT-BU-CPR-26-000041', 10, 1, 3, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-14 23:53:42', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(5, 'NT-BU-CPR-26-000042', 11, 1, 1, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-14 23:54:33', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(6, 'NT-BU-CPR-26-000043', 11, 1, 3, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-14 23:55:00', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(7, 'NT-BU-CPR-26-000044', 10, 1, 3, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-14 23:58:00', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(8, 'NT-BU-CPR-26-000045', 11, 1, 3, 2026, 'brouillon', 75.00, 'CDF', 1.0000, 3, '2026-06-15 00:03:52', 75.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(9, 'NT-BU-CPR-26-000046', 11, 1, 3, 2026, 'brouillon', 49000000.00, 'CDF', 1.0000, 3, '2026-06-15 00:10:48', 49000000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(10, 'NT-BU-CPR-26-000047', 11, 1, 3, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-15 10:17:13', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(11, 'NT-BU-CPR-26-000048', 3, 1, 2, 2026, 'en_attente_liquidation', 204000.00, 'CDF', 1.0000, 3, '2026-06-15 10:38:18', 204000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(12, 'NT-BU-CPR-26-000049', 11, 1, 3, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-15 10:43:24', 45220000.00, 2660000.00, 2660000.00, 0.00, 0.00, 'WEB'),
(13, 'NT-BU-CPR-26-000050', 11, 1, 3, 2026, 'liquidee', 266000000.00, 'CDF', 1.0000, 3, '2026-06-15 10:47:52', 266000000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(14, 'NT-BU-CPR-26-000051', 10, 1, 3, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-15 12:08:50', 1190000.00, 70000.00, 70000.00, 0.00, 0.00, 'WEB'),
(15, 'NT-BU-CPR-26-000052', 9, 1, 3, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-15 12:33:42', 3178000.00, 14000.00, 14000.00, 0.00, 0.00, 'WEB'),
(16, 'NT-BU-CPR-26-000053', 6, 1, 3, 2026, 'brouillon', 3178000.00, 'CDF', 1.0000, 3, '2026-06-15 12:55:38', 3178000.00, 14000.00, 14000.00, 0.00, 0.00, 'WEB'),
(17, 'NT-BU-CPR-26-000054', 11, 1, 3, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-15 12:58:21', 3178000.00, 14000.00, 14000.00, 0.00, 0.00, 'WEB'),
(18, 'NT-BU-CPR-26-000055', 9, 1, 3, 2026, 'liquidee', 3178000.00, 'CDF', 1.0000, 3, '2026-06-15 13:07:54', 3178000.00, 14000.00, 14000.00, 0.00, 0.00, 'WEB'),
(19, 'NT-BU-CPR-26-000056', 12, 1, 3, 2026, 'liquidee', 21028000.00, 'CDF', 1.0000, 3, '2026-06-15 14:49:43', 21028000.00, 14000.00, 14000.00, 230000.00, 0.00, 'WEB'),
(26, 'NT-OFF-26-000001', 19, 1, 4, 2026, 'en_attente_liquidation', 198546000.00, 'CDF', 1.0000, NULL, '2026-06-15 20:07:37', 198546000.00, 0.00, 0.00, 0.00, 0.00, 'PWA_OFFLINE'),
(27, 'NT-OFF-26-000002', 19, 1, 4, 2026, 'en_attente_liquidation', 37805600.00, 'CDF', 1.0000, NULL, '2026-06-15 20:07:39', 37805600.00, 0.00, 0.00, 0.00, 0.00, 'PWA_OFFLINE'),
(28, 'NT-OFF-26-000003', 19, 1, 4, 2026, 'liquidee', 378112000.00, 'CDF', 1.0000, NULL, '2026-06-15 20:07:40', 378112000.00, 0.00, 0.00, 0.00, 0.00, 'PWA_OFFLINE'),
(29, 'NT-OFF-26-000004', 19, 1, 4, 2026, 'liquidee', 3444000000.00, 'CDF', 1.0000, NULL, '2026-06-15 20:07:42', 3444000000.00, 0.00, 0.00, 0.00, 0.00, 'PWA_OFFLINE'),
(30, 'NT-OFF-26-000005', 19, 1, 4, 2026, 'liquidee', 386400.00, 'CDF', 1.0000, NULL, '2026-06-15 20:07:44', 386400.00, 0.00, 0.00, 0.00, 0.00, 'PWA_OFFLINE'),
(31, 'NT-OFF-26-000006', 19, 1, 4, 2026, 'liquidee', 3640000.00, 'CDF', 1.0000, NULL, '2026-06-15 20:07:45', 3640000.00, 0.00, 0.00, 0.00, 0.00, 'PWA_OFFLINE'),
(32, 'NT-OFF-26-000007', 1, 1, 4, 2026, 'liquidee', 5320000.00, 'CDF', 1.0000, NULL, '2026-06-17 14:03:15', 5320000.00, 0.00, 0.00, 0.00, 0.00, 'PWA_OFFLINE'),
(33, 'NT-BU-CPR-26-000057', 19, 1, 2, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-24 15:07:57', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(34, 'NT-BU-CPR-26-000058', 4, 1, 2, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 08:35:38', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(35, 'NT-BU-CPR-26-000059', 19, 1, 1, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 08:58:59', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(36, 'NT-BU-CPR-26-000060', 12, 1, 2, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 08:59:32', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(37, 'NT-BU-CPR-26-000061', 19, 1, 2, 2026, 'brouillon', 270000.00, 'CDF', 1.0000, 3, '2026-06-26 11:38:43', 270000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(38, 'NT-BU-CPR-26-000062', 19, 1, 1, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 11:54:46', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(39, 'NT-BU-CPR-26-000063', 12, 1, 4, 2026, 'brouillon', 15680028.00, 'CDF', 1.0000, 3, '2026-06-26 11:55:30', 15680028.00, 7840000.00, 7840000.00, 0.00, 0.00, 'WEB'),
(40, 'NT-BU-CPR-26-000064', 19, 1, 2, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 12:00:47', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(41, 'NT-BU-CPR-26-000065', 11, 1, 2, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 12:01:10', 1440000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(42, 'NT-BU-CPR-26-000066', 11, 1, 2, 2026, 'brouillon', 1080000.00, 'CDF', 1.0000, 3, '2026-06-26 13:18:58', 1080000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(43, 'NT-BU-CPR-26-000067', 19, 1, 2, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 13:52:56', 960000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(44, 'NT-BU-CPR-26-000068', 11, 1, 2, 2026, 'brouillon', 500000.00, 'CDF', 1.0000, 3, '2026-06-26 14:03:01', 816000.00, 0.00, 0.00, 500000.00, 0.00, 'WEB'),
(45, 'NT-BU-CPR-26-000069', 11, 1, 2, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 14:12:45', 1440000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(46, 'NT-BU-CPR-26-000070', 11, 1, 2, 2026, 'brouillon', 1440000.00, 'CDF', 1.0000, 3, '2026-06-26 14:36:01', 1440000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(47, 'NT-BU-CPR-26-000071', 11, 1, 2, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 15:55:51', 360000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(48, 'NT-BU-CPR-26-000072', 11, 1, 2, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 17:21:01', 1440000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(49, 'NT-BU-CPR-26-000073', 11, 1, 2, 2026, 'liquidee', 720000.00, 'CDF', 1.0000, 3, '2026-06-26 17:33:01', 720000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(50, 'NT-BU-CPR-26-000074', 19, 1, 4, 2026, 'brouillon', 15680028.00, 'CDF', 1.0000, 3, '2026-06-26 17:55:03', 15680028.00, 7840000.00, 7840000.00, 0.00, 0.00, 'WEB'),
(51, 'NT-BU-CPR-26-000075', 10, 1, 4, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 21:31:38', 15680028.00, 7840000.00, 7840000.00, 0.00, 0.00, 'WEB'),
(52, 'NT-BU-CPR-26-000076', 11, 1, 4, 2026, 'brouillon', 5628.00, 'CDF', 1.0000, 3, '2026-06-26 21:47:48', 5628.00, 2800.00, 2800.00, 0.00, 0.00, 'WEB'),
(53, 'NT-BU-CPR-26-000077', 19, 1, 4, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 21:58:42', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(54, 'NT-BU-CPR-26-000078', 11, 1, 4, 2026, 'brouillon', 5628.00, 'CDF', 1.0000, 3, '2026-06-26 22:10:46', 5628.00, 2800.00, 2800.00, 0.00, 0.00, 'WEB'),
(55, 'NT-BU-CPR-26-000079', 10, 1, 4, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 23:00:19', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(56, 'NT-BU-CPR-26-000080', 5, 1, 4, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 3, '2026-06-26 23:01:05', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(57, 'NT-BU-CPR-26-000081', 7, 1, 4, 2026, 'brouillon', 10920.00, 'CDF', 1.0000, 3, '2026-06-26 23:07:47', 5320.00, 2800.00, 2800.00, 0.00, 0.00, 'WEB'),
(58, 'NT-BU-CPR-26-000082', 19, 1, 2, 2026, 'liquidee', 5400000.00, 'CDF', 1.0000, 3, '2026-06-26 23:11:16', 5400000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(59, 'NT-BU-CPR-26-000083', 19, 1, 4, 2026, 'liquidee', 5325600.00, 'CDF', 1.0000, 3, '2026-06-27 00:13:39', 5320000.00, 2800.00, 2800.00, 0.00, 0.00, 'WEB'),
(60, 'NT-TS-KIS-26-000001', 19, 3, 2, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 8, '2026-06-27 14:02:09', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(61, 'NT-TS-KIS-26-000002', 19, 3, 2, 2026, 'brouillon', 0.00, 'CDF', 1.0000, 8, '2026-06-27 14:09:41', 0.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(62, 'NT-TS-KIS-26-000003', 19, 3, 2, 2026, 'brouillon', 90000.00, 'CDF', 1.0000, 8, '2026-06-27 14:14:23', 90000.00, 0.00, 0.00, 0.00, 0.00, 'WEB'),
(63, 'NT-BU-CPR-26-000084', 19, 1, 2, 2026, 'liquidee', 26700027.00, 'CDF', 1.0000, 3, '2026-06-27 17:43:52', 26700027.00, 0.00, 0.00, 0.00, 0.00, 'WEB');

--
-- Déclencheurs `notes_taxation`
--
DELIMITER $$
CREATE TRIGGER `prevent_update_nt` BEFORE UPDATE ON `notes_taxation` FOR EACH ROW BEGIN
    IF OLD.statut NOT IN ('brouillon', '', 'en_attente_liquidation')
       AND NEW.statut <> OLD.statut THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Modification interdite : NT déjà traitée';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `notes_taxation_details`
--

CREATE TABLE `notes_taxation_details` (
  `id` int(11) NOT NULL,
  `note_taxation_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `acte_taxable_id` int(11) DEFAULT NULL,
  `libelle_acte` text DEFAULT NULL,
  `type_calcul` varchar(50) DEFAULT NULL,
  `periode_code` varchar(50) DEFAULT NULL,
  `periode_libelle` varchar(255) DEFAULT NULL,
  `mois_concernes` text DEFAULT NULL,
  `details_calcul` longtext DEFAULT NULL,
  `loyer_mensuel` decimal(18,2) DEFAULT NULL,
  `taux_irl` decimal(10,2) DEFAULT 0.00,
  `taux_rl` decimal(10,2) DEFAULT 0.00,
  `taux_pourcentage` decimal(10,2) DEFAULT 0.00,
  `base_imposable` decimal(18,6) DEFAULT 0.000000,
  `quantite` decimal(18,6) DEFAULT 1.000000,
  `montant_acte` decimal(18,2) DEFAULT 0.00,
  `montant_frais_admin` decimal(18,2) DEFAULT 0.00,
  `montant_frais_tech` decimal(18,2) DEFAULT 0.00,
  `total_ligne` decimal(18,2) DEFAULT 0.00,
  `devise_source` varchar(10) DEFAULT 'USD',
  `taux_change` decimal(18,4) DEFAULT 1.0000,
  `total_ligne_source` decimal(18,2) DEFAULT 0.00,
  `total_ligne_cdf` decimal(18,2) DEFAULT 0.00,
  `direction_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `art_par` varchar(100) DEFAULT NULL,
  `acte_generateur` text DEFAULT NULL,
  `periodicite` varchar(50) DEFAULT NULL,
  `mode_calcul` varchar(50) DEFAULT NULL,
  `unite_assiette` varchar(100) DEFAULT NULL,
  `montant_source` decimal(18,2) DEFAULT 0.00,
  `montant_cdf` decimal(18,2) DEFAULT 0.00,
  `periodicite_info` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notes_taxation_details`
--

INSERT INTO `notes_taxation_details` (`id`, `note_taxation_id`, `article_id`, `acte_taxable_id`, `libelle_acte`, `type_calcul`, `periode_code`, `periode_libelle`, `mois_concernes`, `details_calcul`, `loyer_mensuel`, `taux_irl`, `taux_rl`, `taux_pourcentage`, `base_imposable`, `quantite`, `montant_acte`, `montant_frais_admin`, `montant_frais_tech`, `total_ligne`, `devise_source`, `taux_change`, `total_ligne_source`, `total_ligne_cdf`, `direction_id`, `service_id`, `art_par`, `acte_generateur`, `periodicite`, `mode_calcul`, `unite_assiette`, `montant_source`, `montant_cdf`, `periodicite_info`) VALUES
(3, 3, 1, NULL, 'Impôts sur les revenus locatifs (IRL)', 'irl', 'semestre_1', 'Premier semestre', 'Janvier,Février,Mars,Avril,Mai,Juin', '{\"type_calcul\":\"irl\",\"base\":8400000,\"details\":[{\"libelle\":\"IRL\",\"taux\":10,\"formule\":\"1 400 000,00 × 6 mois × 10%\",\"montant\":840000}],\"total\":840000,\"loyer_mensuel\":1400000,\"nombre_mois\":6,\"periode\":{\"code\":\"premier_semestre\",\"libelle\":\"Premier semestre\",\"nombre_mois\":6,\"mois\":\"Janvier,Février,Mars,Avril,Mai,Juin\"},\"bareme\":{\"irl_physique_non_commercante\":10,\"irl_personne_commercante_et_morale\":15,\"rl\":2,\"mention\":\"Barème revenus locatifs :\\nPersonne physique non commerçante : IRL 10%\\nPersonne commerçante et personne morale : IRL 15%\\nRL : 2% calculé séparément sur le montant du loyer.\"},\"taux_irl_applique\":10,\"taux_rl_applique\":0}', NULL, 10.00, 0.00, 0.00, 8400000.000000, 1.000000, 840000.00, 0.00, 0.00, 840000.00, 'CDF', 1.0000, 840000.00, 840000.00, 1, 2, 'Art.17111600', 'CONTRAT DE BAIL', 'annuelle', 'pourcentage', 'Montant du loyer', 840000.00, 840000.00, 'Premier semestre'),
(4, 3, 4, NULL, 'RETENU LOCATIVE (RL)', 'rl', 'semestre_1', 'Premier semestre', 'Janvier,Février,Mars,Avril,Mai,Juin', '{\"type_calcul\":\"rl\",\"base\":8400000,\"details\":[{\"libelle\":\"RL\",\"taux\":2,\"formule\":\"1 400 000,00 × 6 mois × 2%\",\"montant\":168000}],\"total\":168000,\"loyer_mensuel\":1400000,\"nombre_mois\":6,\"periode\":{\"code\":\"premier_semestre\",\"libelle\":\"Premier semestre\",\"nombre_mois\":6,\"mois\":\"Janvier,Février,Mars,Avril,Mai,Juin\"},\"bareme\":{\"irl_physique_non_commercante\":10,\"irl_personne_commercante_et_morale\":15,\"rl\":2,\"mention\":\"Barème revenus locatifs :\\nPersonne physique non commerçante : IRL 10%\\nPersonne commerçante et personne morale : IRL 15%\\nRL : 2% calculé séparément sur le montant du loyer.\"},\"taux_irl_applique\":0,\"taux_rl_applique\":2}', NULL, 0.00, 2.00, 0.00, 8400000.000000, 1.000000, 168000.00, 0.00, 0.00, 168000.00, 'CDF', 1.0000, 168000.00, 168000.00, 1, 2, 'Art.17111600', 'CONTRAT DE BAIL', 'annuelle', 'pourcentage', 'Montant du loyer', 168000.00, 168000.00, 'Premier semestre'),
(6, 8, 6, NULL, 'Taxe sur licence de fabrication, d\'achat et vente,\r\ndétention, du commerce et toutes opérations\r\nrelatives aux alcools, boissons alcoolisées', 'fixe', 'annuel', NULL, NULL, '{\"type_calcul\":\"fixe\",\"base\":75,\"details\":[{\"libelle\":\"Taxe sur licence de fabrication, d\'achat et vente,\\r\\ndétention, du commerce et toutes opérations\\r\\nrelatives aux alcools, boissons alcoolisées\",\"formule\":\"Montant fixe\",\"montant\":75}],\"total\":75}', NULL, 0.00, 0.00, 0.00, 75.000000, 1.000000, 75.00, 0.00, 0.00, 75.00, 'CDF', 1.0000, 75.00, 75.00, 2, 3, 'Art. 17153140', 'Demande de licence', 'annuelle', 'fixe', '----', 75.00, 75.00, NULL),
(10, 9, 7, NULL, 'Taxe sur licence de fabrication, d\'achat et vente,\r\ndétention, du commerce et toutes opérations\r\nrelatives aux alcools, boissons alcoolisées.', 'par_unite', 'annuel', NULL, NULL, '{\"type_calcul\":\"par_unite\",\"base\":35,\"details\":[{\"libelle\":\"Taxe sur licence de fabrication, d\'achat et vente,\\r\\ndétention, du commerce et toutes opérations\\r\\nrelatives aux alcools, boissons alcoolisées.\",\"formule\":\"35,00 × 1 400 000,00\",\"montant\":49000000}],\"total\":49000000,\"quantite\":35,\"montant_unitaire\":1400000}', NULL, 0.00, 0.00, 0.00, 35.000000, 35.000000, 49000000.00, 0.00, 0.00, 49000000.00, 'CDF', 1.0000, 49000000.00, 49000000.00, 2, 3, 'Art. 17153140', 'Demande de licence', 'ponctuelle', 'par_unite', 'Certificat', 49000000.00, 49000000.00, NULL),
(11, 11, 4, 4, 'RETENU LOCATIVE (RL)', 'rl', 'semestre_1', 'Premier semestre', 'Janvier,Février,Mars,Avril,Mai,Juin', '{\"type_calcul\":\"rl\",\"base\":1200000,\"details\":[{\"libelle\":\"RL\",\"taux\":2,\"formule\":\"200 000,00 × 6 mois × 2%\",\"montant\":24000}],\"total\":24000,\"loyer_mensuel\":200000,\"nombre_mois\":6,\"periode\":{\"code\":\"premier_semestre\",\"libelle\":\"Premier semestre\",\"nombre_mois\":6,\"mois\":\"Janvier,Février,Mars,Avril,Mai,Juin\"},\"bareme\":{\"irl_physique_non_commercante\":10,\"irl_personne_commercante_et_morale\":15,\"rl\":2,\"mention\":\"Barème revenus locatifs :\\nPersonne physique non commerçante : IRL 10%\\nPersonne commerçante et personne morale : IRL 15%\\nRL : 2% calculé séparément sur le montant du loyer.\"},\"taux_irl_applique\":0,\"taux_rl_applique\":2,\"devise_source\":\"CDF\",\"taux_change\":1,\"total_source\":24000,\"total_cdf\":24000,\"regle_saisie\":\"IRL\\/RL : Montant du loyer\"}', 200000.00, 0.00, 2.00, 0.00, 1200000.000000, 6.000000, 24000.00, 0.00, 0.00, 24000.00, 'CDF', 1.0000, 24000.00, 24000.00, 1, 2, 'Art.17111600', 'CONTRAT DE BAIL', 'annuelle', 'pourcentage', 'Montant du loyer', 24000.00, 24000.00, 'Premier semestre'),
(12, 11, 2, 2, 'Impôts sur les revenus locatifs (IRL)', 'irl', 'semestre_1', 'Premier semestre', 'Janvier,Février,Mars,Avril,Mai,Juin', '{\"type_calcul\":\"irl\",\"base\":1200000,\"details\":[{\"libelle\":\"IRL\",\"taux\":15,\"formule\":\"200 000,00 × 6 mois × 15%\",\"montant\":180000}],\"total\":180000,\"loyer_mensuel\":200000,\"nombre_mois\":6,\"periode\":{\"code\":\"premier_semestre\",\"libelle\":\"Premier semestre\",\"nombre_mois\":6,\"mois\":\"Janvier,Février,Mars,Avril,Mai,Juin\"},\"bareme\":{\"irl_physique_non_commercante\":10,\"irl_personne_commercante_et_morale\":15,\"rl\":2,\"mention\":\"Barème revenus locatifs :\\nPersonne physique non commerçante : IRL 10%\\nPersonne commerçante et personne morale : IRL 15%\\nRL : 2% calculé séparément sur le montant du loyer.\"},\"taux_irl_applique\":15,\"taux_rl_applique\":0,\"devise_source\":\"CDF\",\"taux_change\":1,\"total_source\":180000,\"total_cdf\":180000,\"regle_saisie\":\"IRL\\/RL : Montant du loyer\"}', 200000.00, 15.00, 0.00, 0.00, 1200000.000000, 1.000000, 180000.00, 0.00, 0.00, 180000.00, 'CDF', 1.0000, 180000.00, 180000.00, 1, 2, 'Art.17111600', 'CONTRAT DE BAIL', 'annuelle', 'pourcentage', 'Montant du loyer', 180000.00, 180000.00, 'Premier semestre'),
(15, 13, 7, 7, 'Taxe sur licence de fabrication, d\'achat et vente,\r\ndétention, du commerce et toutes opérations\r\nrelatives aux alcools, boissons alcoolisées.', 'par_unite', NULL, NULL, NULL, '{\"type_calcul\":\"par_unite\",\"base\":190,\"principal_source\":266000000,\"frais_admin_source\":0,\"frais_tech_source\":0,\"total\":266000000,\"details\":[{\"libelle\":\"Taxe sur licence de fabrication, d\'achat et vente,\\r\\ndétention, du commerce et toutes opérations\\r\\nrelatives aux alcools, boissons alcoolisées.\",\"formule\":\"190,00 × 1 400 000,00\",\"montant\":266000000}],\"devise_source\":\"CDF\",\"taux_change\":1,\"total_source\":266000000,\"total_cdf\":266000000,\"regle_saisie\":\"Base imposable \\/ Montant\"}', NULL, 0.00, 0.00, 0.00, 190.000000, 190.000000, 266000000.00, 0.00, 0.00, 266000000.00, 'CDF', 1.0000, 266000000.00, 266000000.00, 2, 3, 'Art. 17153140', 'Demande de licence', 'ponctuelle', 'par_unite', 'Certificat', 266000000.00, 266000000.00, 'ponctuelle'),
(20, 16, 6, 6, 'Taxe sur licence de fabrication, d\'achat et vente,\r\ndétention, du commerce et toutes opérations\r\nrelatives aux alcools, boissons alcoolisées', 'par_unite', NULL, NULL, NULL, '{\"type_calcul\":\"par_unite\",\"base\":15,\"principal_source\":1125,\"frais_admin_source\":5,\"frais_tech_source\":5,\"total\":1125,\"details\":[{\"libelle\":\"Taxe sur licence de fabrication, d\'achat et vente,\\r\\ndétention, du commerce et toutes opérations\\r\\nrelatives aux alcools, boissons alcoolisées\",\"formule\":\"15,00 × 75,00\",\"montant\":1125},{\"libelle\":\"Frais administratif\",\"formule\":\"5,00 USD × taux du jour\",\"montant_source_usd\":5},{\"libelle\":\"Frais technique\",\"formule\":\"5,00 USD × taux du jour\",\"montant_source_usd\":5}],\"devise_source\":\"USD\",\"taux_change\":2800,\"principal_cdf\":3150000,\"frais_admin_usd\":5,\"frais_admin_cdf\":14000,\"frais_tech_usd\":5,\"frais_tech_cdf\":14000,\"total_source\":1135,\"total_cdf\":3178000,\"regle_frais\":\"FA\\/FT fixes en USD convertis en CDF et ajoutés au total\",\"regle_saisie\":\"Base imposable \\/ Montant\"}', NULL, 0.00, 0.00, 0.00, 15.000000, 15.000000, 3150000.00, 14000.00, 14000.00, 3178000.00, 'USD', 2800.0000, 1135.00, 3178000.00, 2, 3, 'Art. 17153140', 'Demande de licence', 'annuelle', 'par_unite', 'Nombre des Licences', 1135.00, 3178000.00, 'annuelle'),
(22, 18, 6, 6, 'Taxe sur licence de fabrication, d\'achat et vente,\r\ndétention, du commerce et toutes opérations\r\nrelatives aux alcools, boissons alcoolisées', 'par_unite', NULL, NULL, NULL, '{\"type_calcul\":\"par_unite\",\"base\":15,\"principal_source\":1125,\"frais_admin_source\":5,\"frais_tech_source\":5,\"total\":1125,\"details\":[{\"libelle\":\"Taxe sur licence de fabrication, d\'achat et vente,\\r\\ndétention, du commerce et toutes opérations\\r\\nrelatives aux alcools, boissons alcoolisées\",\"formule\":\"15,00 × 75,00\",\"montant\":1125},{\"libelle\":\"Frais administratif\",\"formule\":\"5,00 USD × taux du jour\",\"montant_source_usd\":5},{\"libelle\":\"Frais technique\",\"formule\":\"5,00 USD × taux du jour\",\"montant_source_usd\":5}],\"devise_source\":\"USD\",\"taux_change\":2800,\"principal_cdf\":3150000,\"frais_admin_usd\":5,\"frais_admin_cdf\":14000,\"frais_tech_usd\":5,\"frais_tech_cdf\":14000,\"total_source\":1135,\"total_cdf\":3178000,\"regle_frais\":\"FA\\/FT fixes en USD convertis en CDF et ajoutés au total\",\"regle_saisie\":\"Base imposable \\/ Montant\"}', NULL, 0.00, 0.00, 0.00, 15.000000, 15.000000, 3150000.00, 14000.00, 14000.00, 3178000.00, 'USD', 2800.0000, 1135.00, 3178000.00, 2, 3, 'Art. 17153140', 'Demande de licence', 'annuelle', 'par_unite', 'Nombre des Licences', 1135.00, 3178000.00, 'annuelle'),
(23, 19, 6, 6, 'Taxe sur licence de fabrication, d\'achat et vente,\r\ndétention, du commerce et toutes opérations\r\nrelatives aux alcools, boissons alcoolisées', 'par_unite', NULL, NULL, NULL, '{\"type_calcul\":\"par_unite\",\"base\":100,\"principal_source\":7500,\"frais_admin_source\":5,\"frais_tech_source\":5,\"total\":7500,\"details\":[{\"libelle\":\"Taxe sur licence de fabrication, d\'achat et vente,\\r\\ndétention, du commerce et toutes opérations\\r\\nrelatives aux alcools, boissons alcoolisées\",\"formule\":\"100,00 × 75,00\",\"montant\":7500},{\"libelle\":\"Frais administratif\",\"formule\":\"5,00 USD × taux du jour\",\"montant_source_usd\":5},{\"libelle\":\"Frais technique\",\"formule\":\"5,00 USD × taux du jour\",\"montant_source_usd\":5}],\"devise_source\":\"USD\",\"taux_change\":2800,\"principal_cdf\":21000000,\"frais_admin_usd\":5,\"frais_admin_cdf\":14000,\"frais_tech_usd\":5,\"frais_tech_cdf\":14000,\"total_source\":7510,\"total_cdf\":21028000,\"regle_frais\":\"FA\\/FT fixes en USD convertis en CDF et ajoutés au total\",\"regle_saisie\":\"Base imposable \\/ Montant\"}', NULL, 0.00, 0.00, 0.00, 100.000000, 100.000000, 21000000.00, 14000.00, 14000.00, 21028000.00, 'USD', 2800.0000, 7510.00, 21028000.00, 2, 3, 'Art. 17153140', 'Demande de licence', 'annuelle', 'par_unite', 'Nombre des Licences', 7510.00, 21028000.00, 'annuelle'),
(30, 26, 8, 8, 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'fixe', NULL, 'Taxation spontanée PWA Offline', NULL, '{\"source\":\"PWA_OFFLINE\",\"type_taxe\":\"chargement\",\"plaque\":\"CGO00045\",\"gps\":{\"lat\":null,\"lng\":null},\"calcul\":{\"base_imposable\":0,\"quantite\":9864200,\"montant_cdf\":198546000},\"article\":{\"id\":8,\"code_article\":\"27022450\",\"nature_acte\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\"},\"mention\":\"Taxation créée automatiquement depuis la PWA Offline\"}', NULL, 0.00, 0.00, 0.00, 0.000000, 9864200.000000, 198546000.00, 0.00, 0.00, 198546000.00, 'CDF', 1.0000, 198546000.00, 198546000.00, 2, 4, '27022450', 'Mise sur le marché des matières non biodégradables', 'ponctuelle', 'fixe', 'Nombre', 198546000.00, 198546000.00, 'ponctuelle'),
(31, 27, 8, 8, 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'fixe', NULL, 'Taxation spontanée PWA Offline', NULL, '{\"source\":\"PWA_OFFLINE\",\"type_taxe\":\"autre\",\"plaque\":\"CGO00934\",\"gps\":{\"lat\":null,\"lng\":null},\"calcul\":{\"base_imposable\":0,\"quantite\":1350000,\"montant_cdf\":37805600},\"article\":{\"id\":8,\"code_article\":\"27022450\",\"nature_acte\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\"},\"mention\":\"Taxation créée automatiquement depuis la PWA Offline\"}', NULL, 0.00, 0.00, 0.00, 0.000000, 1350000.000000, 37805600.00, 0.00, 0.00, 37805600.00, 'CDF', 1.0000, 37805600.00, 37805600.00, 2, 4, '27022450', 'Mise sur le marché des matières non biodégradables', 'ponctuelle', 'fixe', 'Nombre', 37805600.00, 37805600.00, 'ponctuelle'),
(32, 28, 8, 8, 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'fixe', NULL, 'Taxation spontanée PWA Offline', NULL, '{\"source\":\"PWA_OFFLINE\",\"type_taxe\":\"chargement\",\"plaque\":\"CHK00023\",\"gps\":{\"lat\":null,\"lng\":null},\"calcul\":{\"base_imposable\":0,\"quantite\":13504000,\"montant_cdf\":378112000},\"article\":{\"id\":8,\"code_article\":\"27022450\",\"nature_acte\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\"},\"mention\":\"Taxation créée automatiquement depuis la PWA Offline\"}', NULL, 0.00, 0.00, 0.00, 0.000000, 13504000.000000, 378112000.00, 0.00, 0.00, 378112000.00, 'CDF', 1.0000, 378112000.00, 378112000.00, 2, 4, '27022450', 'Mise sur le marché des matières non biodégradables', 'ponctuelle', 'fixe', 'Nombre', 378112000.00, 378112000.00, 'ponctuelle'),
(33, 29, 8, 8, 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'fixe', NULL, 'Taxation spontanée PWA Offline', NULL, '{\"source\":\"PWA_OFFLINE\",\"type_taxe\":\"autre\",\"plaque\":\"CGOT00023\",\"gps\":{\"lat\":null,\"lng\":null},\"calcul\":{\"base_imposable\":0,\"quantite\":123000000,\"montant_cdf\":3444000000},\"article\":{\"id\":8,\"code_article\":\"27022450\",\"nature_acte\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\"},\"mention\":\"Taxation créée automatiquement depuis la PWA Offline\"}', NULL, 0.00, 0.00, 0.00, 0.000000, 123000000.000000, 3444000000.00, 0.00, 0.00, 3444000000.00, 'CDF', 1.0000, 3444000000.00, 3444000000.00, 2, 4, '27022450', 'Mise sur le marché des matières non biodégradables', 'ponctuelle', 'fixe', 'Nombre', 3444000000.00, 3444000000.00, 'ponctuelle'),
(34, 30, 8, 8, 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'fixe', NULL, 'Taxation spontanée PWA Offline', NULL, '{\"source\":\"PWA_OFFLINE\",\"type_taxe\":\"autre\",\"plaque\":\"RIRJB00023\",\"gps\":{\"lat\":null,\"lng\":null},\"calcul\":{\"base_imposable\":0,\"quantite\":13800,\"montant_cdf\":386400},\"article\":{\"id\":8,\"code_article\":\"27022450\",\"nature_acte\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\"},\"mention\":\"Taxation créée automatiquement depuis la PWA Offline\"}', NULL, 0.00, 0.00, 0.00, 0.000000, 13800.000000, 386400.00, 0.00, 0.00, 386400.00, 'CDF', 1.0000, 386400.00, 386400.00, 2, 4, '27022450', 'Mise sur le marché des matières non biodégradables', 'ponctuelle', 'fixe', 'Nombre', 386400.00, 386400.00, 'ponctuelle'),
(35, 31, 8, 8, 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'fixe', NULL, 'Taxation spontanée PWA Offline', NULL, '{\"source\":\"PWA_OFFLINE\",\"type_taxe\":\"autre\",\"plaque\":\"CDO00034\",\"gps\":{\"lat\":null,\"lng\":null},\"calcul\":{\"base_imposable\":0,\"quantite\":130000,\"montant_cdf\":3640000},\"article\":{\"id\":8,\"code_article\":\"27022450\",\"nature_acte\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\"},\"mention\":\"Taxation créée automatiquement depuis la PWA Offline\"}', NULL, 0.00, 0.00, 0.00, 0.000000, 130000.000000, 3640000.00, 0.00, 0.00, 3640000.00, 'CDF', 1.0000, 3640000.00, 3640000.00, 2, 4, '27022450', 'Mise sur le marché des matières non biodégradables', 'ponctuelle', 'fixe', 'Nombre', 3640000.00, 3640000.00, 'ponctuelle'),
(36, 32, 8, 8, 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'fixe', NULL, 'Taxation spontanée PWA Offline', NULL, '{\"source\":\"PWA_OFFLINE\",\"type_taxe\":\"dechargement\",\"plaque\":\"CD08493\",\"gps\":{\"lat\":-4.3613891,\"lng\":15.1940353},\"calcul\":{\"base_imposable\":0,\"quantite\":190000,\"montant_cdf\":5320000},\"article\":{\"id\":8,\"code_article\":\"27022450\",\"nature_acte\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\"},\"mention\":\"Taxation créée automatiquement depuis la PWA Offline\"}', NULL, 0.00, 0.00, 0.00, 0.000000, 190000.000000, 5320000.00, 0.00, 0.00, 5320000.00, 'CDF', 1.0000, 5320000.00, 5320000.00, 2, 4, '27022450', 'Mise sur le marché des matières non biodégradables', 'ponctuelle', 'fixe', 'Nombre', 5320000.00, 5320000.00, 'ponctuelle'),
(37, 37, 2, NULL, 'Impôts sur les revenus locatifs (IRL)', 'irl', 'trimestriel', 'Trimestriel', '3 mois sélectionnés', '{\"type_calcul\":\"irl\",\"base\":2700000,\"details\":[{\"libelle\":\"IRL\",\"taux\":10,\"formule\":\"900 000,00 × 3 mois × 10%\",\"montant\":270000}],\"total\":270000,\"loyer_mensuel\":900000,\"nombre_mois\":3,\"periode\":{\"code\":\"trimestriel\",\"libelle\":\"Trimestriel\",\"nombre_mois\":3,\"mois\":\"3 mois sélectionnés\"},\"bareme\":{\"irl_physique_non_commercante\":10,\"irl_personne_commercante_et_morale\":15,\"rl\":2,\"mention\":\"Barème revenus locatifs :\\nPersonne physique non commerçante : IRL 10%\\nPersonne commerçante et personne morale : IRL 15%\\nRL : 2% calculé séparément sur le montant du loyer.\"},\"taux_irl_applique\":10,\"taux_rl_applique\":0}', NULL, 10.00, 0.00, 0.00, 2700000.000000, 1.000000, 270000.00, 0.00, 0.00, 270000.00, 'CDF', 1.0000, 270000.00, 270000.00, 1, 2, 'Art.17111600', 'CONTRAT DE BAIL', 'annuelle', 'pourcentage', 'Montant du loyer', 270000.00, 270000.00, 'Trimestriel'),
(38, 39, 8, 8, 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'fixe', NULL, NULL, NULL, '{\"type_calcul\":\"fixe\",\"base\":0,\"principal_source\":28,\"frais_admin_source\":2800,\"frais_tech_source\":2800,\"total\":28,\"details\":[{\"libelle\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\",\"formule\":\"Montant fixe\",\"montant\":28},{\"libelle\":\"Frais administratif\",\"formule\":\"2 800,00 USD × taux du jour\",\"montant_source_usd\":2800},{\"libelle\":\"Frais technique\",\"formule\":\"2 800,00 USD × taux du jour\",\"montant_source_usd\":2800}],\"devise_source\":\"CDF\",\"taux_change\":2800,\"principal_cdf\":28,\"frais_admin_usd\":2800,\"frais_admin_cdf\":7840000,\"frais_tech_usd\":2800,\"frais_tech_cdf\":7840000,\"total_source\":5628,\"total_cdf\":15680028,\"regle_frais\":\"FA\\/FT fixes en USD convertis en CDF et ajoutés au total\",\"regle_saisie\":\"Base imposable \\/ Montant\"}', NULL, 0.00, 0.00, 0.00, 0.000000, 190.000000, 28.00, 7840000.00, 7840000.00, 15680028.00, 'CDF', 2800.0000, 5628.00, 15680028.00, 2, 4, 'aRT. 27022450', 'Mise sur le marché des\r\nmatières non\r\nbiodégradables', 'ponctuelle', 'fixe', 'Nombre', 5628.00, 15680028.00, 'ponctuelle'),
(43, 42, 1, 1, 'Impôts sur les revenus locatifs (IRL)', 'irl', 'semestre_1', NULL, NULL, '{\"type_calcul\":\"irl\",\"base\":4800000,\"base_imposable\":4800000,\"loyer_mensuel\":800000,\"mois\":6,\"mois_concernes\":6,\"quantite\":1,\"taux\":15,\"taux_pourcentage\":15,\"periode_code\":\"semestre_1\",\"periode_libelle\":\"Semestriel\",\"total\":720000,\"principal_source\":720000,\"montant_source\":720000,\"details_calcul\":\"IRL : 800 000,00 × 6 mois × 15%\",\"details\":[{\"libelle\":\"IRL - Semestriel\",\"formule\":\"IRL : 800 000,00 × 6 mois × 15%\",\"montant\":720000}],\"devise_source\":\"CDF\",\"taux_change\":2800,\"principal_cdf\":720000,\"frais_admin_usd\":0,\"frais_admin_cdf\":0,\"frais_tech_usd\":0,\"frais_tech_cdf\":0,\"total_source\":720000,\"total_cdf\":720000,\"regle_frais\":\"FA\\/FT fixes en USD convertis en CDF et ajoutés au total\",\"regle_saisie\":\"IRL\\/RL : Montant du loyer\"}', 800000.00, 0.00, 0.00, 0.00, 4800000.000000, 1.000000, 720000.00, 0.00, 0.00, 720000.00, 'CDF', 2800.0000, 720000.00, 720000.00, 1, 2, 'Art.17111600', 'CONTRAT DE BAIL', 'annuelle', 'pourcentage', 'Montant du loyer', 720000.00, 720000.00, 'annuelle'),
(44, 42, 1, 1, 'Impôts sur les revenus locatifs (IRL)', 'irl', 'trimestriel', NULL, NULL, '{\"type_calcul\":\"irl\",\"base\":2400000,\"base_imposable\":2400000,\"loyer_mensuel\":800000,\"mois\":3,\"mois_concernes\":3,\"quantite\":1,\"taux\":15,\"taux_pourcentage\":15,\"periode_code\":\"trimestriel\",\"periode_libelle\":\"Trimestriel\",\"total\":360000,\"principal_source\":360000,\"montant_source\":360000,\"details_calcul\":\"IRL : 800 000,00 × 3 mois × 15%\",\"details\":[{\"libelle\":\"IRL - Trimestriel\",\"formule\":\"IRL : 800 000,00 × 3 mois × 15%\",\"montant\":360000}],\"devise_source\":\"CDF\",\"taux_change\":2800,\"principal_cdf\":360000,\"frais_admin_usd\":0,\"frais_admin_cdf\":0,\"frais_tech_usd\":0,\"frais_tech_cdf\":0,\"total_source\":360000,\"total_cdf\":360000,\"regle_frais\":\"FA\\/FT fixes en USD convertis en CDF et ajoutés au total\",\"regle_saisie\":\"IRL\\/RL : Montant du loyer\"}', 800000.00, 0.00, 0.00, 0.00, 2400000.000000, 1.000000, 360000.00, 0.00, 0.00, 360000.00, 'CDF', 2800.0000, 360000.00, 360000.00, 1, 2, 'Art.17111600', 'CONTRAT DE BAIL', 'annuelle', 'pourcentage', 'Montant du loyer', 360000.00, 360000.00, 'annuelle'),
(49, 46, 1, 1, 'Impôts sur les revenus locatifs (IRL)', 'irl', 'semestre_1', NULL, NULL, '{\"type_calcul\":\"irl\",\"type_personne\":\"personne_morale\",\"periode_code\":\"annuel\",\"periode_libelle\":\"Annuel\",\"mois_concernes\":12,\"mois_liste\":\"Janvier, Février, Mars, Avril, Mai, Juin, Juillet, Août, Septembre, Octobre, Novembre, Décembre\",\"mois_texte\":\"Janvier, Février, Mars, Avril, Mai, Juin, Juillet, Août, Septembre, Octobre, Novembre, Décembre\",\"loyer_mensuel\":800000,\"base\":9600000,\"base_imposable\":9600000,\"quantite\":1,\"taux\":15,\"taux_pourcentage\":15,\"total\":1440000,\"principal_source\":1440000,\"montant_source\":1440000,\"details_calcul\":\"IRL : 800 000,00 × 12 mois × 15% | Mois : Janvier, Février, Mars, Avril, Mai, Juin, Juillet, Août, Septembre, Octobre, Novembre, Décembre\",\"details\":[{\"libelle\":\"IRL - Annuel\",\"formule\":\"9 600 000,00 × 15% = 1 440 000,00 CDF\",\"mois\":\"Janvier, Février, Mars, Avril, Mai, Juin, Juillet, Août, Septembre, Octobre, Novembre, Décembre\",\"montant\":1440000,\"taux_affiche\":\"15%\"}],\"devise_source\":\"CDF\",\"taux_change\":2800,\"principal_cdf\":1440000,\"frais_admin_usd\":0,\"frais_admin_cdf\":0,\"frais_tech_usd\":0,\"frais_tech_cdf\":0,\"total_source\":1440000,\"total_cdf\":1440000,\"regle_frais\":\"FA\\/FT fixes en USD convertis en CDF et ajoutés au total\",\"regle_saisie\":\"IRL\\/RL : Montant du loyer\"}', 800000.00, 0.00, 0.00, 0.00, 9600000.000000, 1.000000, 1440000.00, 0.00, 0.00, 1440000.00, 'CDF', 2800.0000, 1440000.00, 1440000.00, 1, 2, 'Art.17111600', 'CONTRAT DE BAIL', 'annuelle', 'pourcentage', 'Montant du loyer', 1440000.00, 1440000.00, 'annuelle'),
(52, 49, 1, 1, 'Impôts sur les revenus locatifs (IRL)', 'irl', 's1', '1er Semestre', '6', '{\"type_calcul\":\"irl\",\"type_personne\":\"personne_morale\",\"periode_code\":\"s1\",\"periode_libelle\":\"1er Semestre\",\"mois_concernes\":6,\"mois_liste\":\"Janvier, Février, Mars, Avril, Mai, Juin\",\"mois_texte\":\"Janvier, Février, Mars, Avril, Mai, Juin\",\"loyer_mensuel\":800000,\"base\":4800000,\"base_imposable\":4800000,\"quantite\":1,\"taux\":15,\"taux_pourcentage\":15,\"total\":720000,\"principal_source\":720000,\"montant_source\":720000,\"details_calcul\":\"IRL : 800 000,00 × 6 mois × 15% | Mois : Janvier, Février, Mars, Avril, Mai, Juin\",\"details\":[{\"libelle\":\"IRL - 1er Semestre\",\"formule\":\"4 800 000,00 × 15% = 720 000,00 CDF\",\"mois\":\"Janvier, Février, Mars, Avril, Mai, Juin\",\"montant\":720000,\"taux_affiche\":\"15%\"}],\"devise_source\":\"CDF\",\"taux_change\":2800,\"principal_cdf\":720000,\"frais_admin_usd\":0,\"frais_admin_cdf\":0,\"frais_tech_usd\":0,\"frais_tech_cdf\":0,\"total_source\":720000,\"total_cdf\":720000,\"regle_frais\":\"FA\\/FT fixes en USD convertis en CDF et ajoutés au total\",\"regle_saisie\":\"IRL\\/RL : Montant du loyer\",\"periode_affichee\":\"1er Semestre\"}', 800000.00, 0.00, 0.00, 0.00, 4800000.000000, 1.000000, 720000.00, 0.00, 0.00, 720000.00, 'CDF', 2800.0000, 720000.00, 720000.00, 1, 2, 'Art.17111600', 'CONTRAT DE BAIL', 'annuelle', 'pourcentage', 'Montant du loyer', 720000.00, 720000.00, '1er Semestre'),
(53, 50, 8, 8, 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'fixe', 'm1', 'Janvier', '1', '{\"type_calcul\":\"fixe\",\"base\":0,\"principal_source\":28,\"frais_admin_source\":2800,\"frais_tech_source\":2800,\"total\":28,\"details\":[{\"libelle\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\",\"formule\":\"Montant fixe\",\"montant\":28},{\"libelle\":\"Frais administratif\",\"formule\":\"2 800,00 USD × taux du jour\",\"montant_source_usd\":2800},{\"libelle\":\"Frais technique\",\"formule\":\"2 800,00 USD × taux du jour\",\"montant_source_usd\":2800}],\"devise_source\":\"CDF\",\"taux_change\":2800,\"principal_cdf\":28,\"frais_admin_usd\":2800,\"frais_admin_cdf\":7840000,\"frais_tech_usd\":2800,\"frais_tech_cdf\":7840000,\"total_source\":5628,\"total_cdf\":15680028,\"regle_frais\":\"FA\\/FT fixes en USD convertis en CDF et ajoutés au total\",\"regle_saisie\":\"Base imposable \\/ Montant\",\"periode_affichee\":\"Janvier\",\"mois_liste\":\"Janvier\"}', NULL, 0.00, 0.00, 0.00, 0.000000, 190.000000, 28.00, 7840000.00, 7840000.00, 15680028.00, 'CDF', 2800.0000, 5628.00, 15680028.00, 2, 4, 'aRT. 27022450', 'Mise sur le marché des\r\nmatières non\r\nbiodégradables', 'ponctuelle', 'fixe', 'Nombre', 5628.00, 15680028.00, 'Janvier'),
(55, 52, 8, 8, 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'fixe', 'ponctuelle', 'Ponctuelle', NULL, '{\"type_calcul\":\"fixe\",\"base\":0,\"principal_source\":28,\"frais_admin_source\":2800,\"frais_tech_source\":2800,\"total\":28,\"details\":[{\"libelle\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\",\"formule\":\"Montant fixe : 28,00 CDF\",\"montant\":28,\"devise\":\"CDF\"},{\"libelle\":\"Frais administratif\",\"formule\":\"2 800,00 CDF\",\"montant_source\":2800,\"devise\":\"CDF\"},{\"libelle\":\"Frais technique\",\"formule\":\"2 800,00 CDF\",\"montant_source\":2800,\"devise\":\"CDF\"}],\"periode\":{\"code\":\"ponctuelle\",\"libelle\":\"Ponctuelle\",\"mois\":null,\"mois_liste\":null},\"devise_source\":\"CDF\",\"taux_change\":2800,\"principal_cdf\":28,\"frais_admin_usd\":2800,\"frais_admin_cdf\":2800,\"frais_tech_usd\":2800,\"frais_tech_cdf\":2800,\"total_source\":5628,\"total_cdf\":5628,\"regle_frais\":\"FA\\/FT suivent la devise de l’article. Si USD, conversion en CDF. Si CDF, pas de conversion abusive.\",\"regle_saisie\":\"TAXE : Quantité × taux\",\"periode_affichee\":\"Ponctuelle\",\"mois_liste\":null}', NULL, 0.00, 0.00, 0.00, 0.000000, 190.000000, 28.00, 2800.00, 2800.00, 5628.00, 'CDF', 2800.0000, 5628.00, 5628.00, 2, 4, 'aRT. 27022450', 'Mise sur le marché des\r\nmatières non\r\nbiodégradables', 'ponctuelle', 'fixe', 'Nombre', 5628.00, 5628.00, 'Ponctuelle'),
(56, 54, 8, 8, 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'fixe', 'ponctuelle', 'Ponctuelle', NULL, '{\"type_calcul\":\"fixe\",\"base\":0,\"principal_source\":28,\"frais_admin_source\":2800,\"frais_tech_source\":2800,\"total\":28,\"details\":[{\"libelle\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\",\"formule\":\"Montant fixe : 28,00 CDF\",\"montant\":28,\"devise\":\"CDF\"},{\"libelle\":\"Frais administratif\",\"formule\":\"2 800,00 CDF\",\"montant_source\":2800,\"devise\":\"CDF\"},{\"libelle\":\"Frais technique\",\"formule\":\"2 800,00 CDF\",\"montant_source\":2800,\"devise\":\"CDF\"}],\"periode\":{\"code\":\"ponctuelle\",\"libelle\":\"Ponctuelle\",\"mois\":null,\"mois_liste\":null},\"devise_source\":\"CDF\",\"taux_change\":2800,\"principal_cdf\":28,\"frais_admin_usd\":2800,\"frais_admin_cdf\":2800,\"frais_tech_usd\":2800,\"frais_tech_cdf\":2800,\"total_source\":5628,\"total_cdf\":5628,\"regle_frais\":\"FA\\/FT suivent la devise de l’article. Si USD, conversion en CDF. Si CDF, pas de conversion abusive.\",\"regle_saisie\":\"TAXE : Quantité × taux\",\"periode_affichee\":\"Ponctuelle\",\"mois_liste\":null}', NULL, 0.00, 0.00, 0.00, 0.000000, 190.000000, 28.00, 2800.00, 2800.00, 5628.00, 'CDF', 2800.0000, 5628.00, 5628.00, 2, 4, 'aRT. 27022450', 'Mise sur le marché des\r\nmatières non\r\nbiodégradables', 'ponctuelle', 'fixe', 'Nombre', 5628.00, 5628.00, 'Ponctuelle'),
(57, 57, 8, 8, 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'par_unite', 'ponctuelle', 'Ponctuelle', '0', '{\"engine\":\"FiscalEngineRefonte\",\"article\":{\"id\":8,\"code_article\":\"27022450\",\"nature_acte\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\",\"libelle_taux\":\"a) Carte prépayée\"},\"calcul\":{\"type_calcul\":\"par_unite\",\"type_personne\":\"personne_physique_non_commercante\",\"periode_code\":\"ponctuelle\",\"periode_libelle\":\"Ponctuelle\",\"mois_concernes\":0,\"mois_liste\":\"\",\"mois_texte\":\"\",\"loyer_mensuel\":0,\"base\":190,\"base_imposable\":190,\"quantite\":190,\"taux\":28,\"taux_pourcentage\":0,\"principal_source\":5320,\"frais_admin_source\":2800,\"frais_tech_source\":2800,\"total_source\":10920,\"montant_acte_cdf\":5320,\"montant_frais_admin_cdf\":2800,\"montant_frais_tech_cdf\":2800,\"total_ligne_cdf\":10920,\"devise_source\":\"CDF\",\"taux_change\":1,\"details_calcul\":\"{\\\"details\\\":[{\\\"libelle\\\":\\\"Taxe de mise sur le marché des matières non\\\\r\\\\nbiodégradables (cartes prép. Mèches)\\\",\\\"formule\\\":\\\"190,00 × 28,00 CDF\\\",\\\"montant_source\\\":5320,\\\"montant_cdf\\\":5320,\\\"devise\\\":\\\"CDF\\\"},{\\\"libelle\\\":\\\"Frais administratif\\\",\\\"formule\\\":\\\"2 800,00 CDF\\\",\\\"montant_source\\\":2800,\\\"montant_cdf\\\":2800,\\\"devise\\\":\\\"CDF\\\"},{\\\"libelle\\\":\\\"Frais technique\\\",\\\"formule\\\":\\\"2 800,00 CDF\\\",\\\"montant_source\\\":2800,\\\"montant_cdf\\\":2800,\\\"devise\\\":\\\"CDF\\\"}],\\\"periode\\\":{\\\"code\\\":\\\"ponctuelle\\\",\\\"libelle\\\":\\\"Ponctuelle\\\",\\\"nombre_mois\\\":0,\\\"mois_nums\\\":[],\\\"mois_liste\\\":\\\"\\\",\\\"mois_texte\\\":\\\"\\\"},\\\"devise_source\\\":\\\"CDF\\\",\\\"taux_change\\\":1,\\\"total_cdf\\\":10920}\",\"details\":[{\"libelle\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\",\"formule\":\"190,00 × 28,00 CDF\",\"montant_source\":5320,\"montant_cdf\":5320,\"devise\":\"CDF\"},{\"libelle\":\"Frais administratif\",\"formule\":\"2 800,00 CDF\",\"montant_source\":2800,\"montant_cdf\":2800,\"devise\":\"CDF\"},{\"libelle\":\"Frais technique\",\"formule\":\"2 800,00 CDF\",\"montant_source\":2800,\"montant_cdf\":2800,\"devise\":\"CDF\"}]},\"details\":[{\"libelle\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\",\"formule\":\"190,00 × 28,00 CDF\",\"montant_source\":5320,\"montant_cdf\":5320,\"devise\":\"CDF\"},{\"libelle\":\"Frais administratif\",\"formule\":\"2 800,00 CDF\",\"montant_source\":2800,\"montant_cdf\":2800,\"devise\":\"CDF\"},{\"libelle\":\"Frais technique\",\"formule\":\"2 800,00 CDF\",\"montant_source\":2800,\"montant_cdf\":2800,\"devise\":\"CDF\"}],\"periode\":{\"code\":\"ponctuelle\",\"libelle\":\"Ponctuelle\",\"mois_concernes\":0,\"mois_liste\":\"\"}}', 0.00, 0.00, 0.00, 0.00, 190.000000, 190.000000, 5320.00, 2800.00, 2800.00, 10920.00, 'CDF', 1.0000, 10920.00, 10920.00, NULL, NULL, NULL, NULL, 'ponctuelle', 'par_unite', NULL, 0.00, 0.00, 'Ponctuelle'),
(58, 58, 1, 1, 'Impôts sur les revenus locatifs (IRL)', 'irl', 'semestre_1', 'Premier semestre', '6', '{\"engine\":\"FiscalEngineRefonte\",\"article\":{\"id\":1,\"code_article\":\"17111600\",\"nature_acte\":\"Impôts sur les revenus locatifs (IRL)\",\"libelle_taux\":\"a) Personne physique non commerçante\"},\"calcul\":{\"type_calcul\":\"irl\",\"type_personne\":\"personne_physique_non_commercante\",\"periode_code\":\"semestre_1\",\"periode_libelle\":\"Premier semestre\",\"mois_concernes\":6,\"mois_liste\":\"Janvier, Février, Mars, Avril, Mai, Juin\",\"mois_texte\":\"Janvier, Février, Mars, Avril, Mai, Juin\",\"loyer_mensuel\":9000000,\"base\":54000000,\"base_imposable\":54000000,\"quantite\":1,\"taux\":10,\"taux_pourcentage\":10,\"principal_source\":5400000,\"frais_admin_source\":0,\"frais_tech_source\":0,\"total_source\":5400000,\"montant_acte_cdf\":5400000,\"montant_frais_admin_cdf\":0,\"montant_frais_tech_cdf\":0,\"total_ligne_cdf\":5400000,\"devise_source\":\"CDF\",\"taux_change\":1,\"details_calcul\":\"{\\\"details\\\":[{\\\"libelle\\\":\\\"Impôts sur les revenus locatifs (IRL)\\\",\\\"formule\\\":\\\"54 000 000,00 CDF × 10%\\\",\\\"montant_source\\\":5400000,\\\"montant_cdf\\\":5400000,\\\"devise\\\":\\\"CDF\\\",\\\"mois\\\":\\\"Janvier, Février, Mars, Avril, Mai, Juin\\\"}],\\\"periode\\\":{\\\"code\\\":\\\"semestre_1\\\",\\\"libelle\\\":\\\"Premier semestre\\\",\\\"nombre_mois\\\":6,\\\"mois_nums\\\":[1,2,3,4,5,6],\\\"mois_liste\\\":\\\"Janvier, Février, Mars, Avril, Mai, Juin\\\",\\\"mois_texte\\\":\\\"Janvier, Février, Mars, Avril, Mai, Juin\\\"},\\\"devise_source\\\":\\\"CDF\\\",\\\"taux_change\\\":1,\\\"total_cdf\\\":5400000}\",\"details\":[{\"libelle\":\"Impôts sur les revenus locatifs (IRL)\",\"formule\":\"54 000 000,00 CDF × 10%\",\"montant_source\":5400000,\"montant_cdf\":5400000,\"devise\":\"CDF\",\"mois\":\"Janvier, Février, Mars, Avril, Mai, Juin\"}]},\"details\":[{\"libelle\":\"Impôts sur les revenus locatifs (IRL)\",\"formule\":\"54 000 000,00 CDF × 10%\",\"montant_source\":5400000,\"montant_cdf\":5400000,\"devise\":\"CDF\",\"mois\":\"Janvier, Février, Mars, Avril, Mai, Juin\"}],\"periode\":{\"code\":\"semestre_1\",\"libelle\":\"Premier semestre\",\"mois_concernes\":6,\"mois_liste\":\"Janvier, Février, Mars, Avril, Mai, Juin\"}}', 9000000.00, 10.00, 0.00, 10.00, 54000000.000000, 1.000000, 5400000.00, 0.00, 0.00, 5400000.00, 'CDF', 1.0000, 5400000.00, 5400000.00, NULL, NULL, NULL, NULL, 'annuelle', 'irl', NULL, 0.00, 0.00, 'Premier semestre'),
(60, 59, 8, 8, 'Taxe de mise sur le marché des matières non\r\nbiodégradables (cartes prép. Mèches)', 'par_unite', 'ponctuelle', 'Ponctuelle', '0', '{\"engine\":\"FiscalEngineRefonte\",\"article\":{\"id\":8,\"code_article\":\"27022450\",\"nature_acte\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\",\"libelle_taux\":\"a) Carte prépayée\"},\"calcul\":{\"type_calcul\":\"par_unite\",\"type_personne\":\"personne_physique_non_commercante\",\"periode_code\":\"ponctuelle\",\"periode_libelle\":\"Ponctuelle\",\"mois_concernes\":0,\"mois_liste\":\"\",\"mois_texte\":\"\",\"loyer_mensuel\":0,\"base\":190000,\"base_imposable\":190000,\"quantite\":190000,\"taux\":28,\"taux_pourcentage\":0,\"principal_source\":5320000,\"frais_admin_source\":2800,\"frais_tech_source\":2800,\"total_source\":5325600,\"montant_acte_cdf\":5320000,\"montant_frais_admin_cdf\":2800,\"montant_frais_tech_cdf\":2800,\"total_ligne_cdf\":5325600,\"devise_source\":\"CDF\",\"taux_change\":1,\"details_calcul\":\"{\\\"details\\\":[{\\\"libelle\\\":\\\"Taxe de mise sur le marché des matières non\\\\r\\\\nbiodégradables (cartes prép. Mèches)\\\",\\\"formule\\\":\\\"190 000,00 × 28,00 CDF\\\",\\\"montant_source\\\":5320000,\\\"montant_cdf\\\":5320000,\\\"devise\\\":\\\"CDF\\\"},{\\\"libelle\\\":\\\"Frais administratif\\\",\\\"formule\\\":\\\"2 800,00 CDF\\\",\\\"montant_source\\\":2800,\\\"montant_cdf\\\":2800,\\\"devise\\\":\\\"CDF\\\"},{\\\"libelle\\\":\\\"Frais technique\\\",\\\"formule\\\":\\\"2 800,00 CDF\\\",\\\"montant_source\\\":2800,\\\"montant_cdf\\\":2800,\\\"devise\\\":\\\"CDF\\\"}],\\\"periode\\\":{\\\"code\\\":\\\"ponctuelle\\\",\\\"libelle\\\":\\\"Ponctuelle\\\",\\\"nombre_mois\\\":0,\\\"mois_nums\\\":[],\\\"mois_liste\\\":\\\"\\\",\\\"mois_texte\\\":\\\"\\\"},\\\"devise_source\\\":\\\"CDF\\\",\\\"taux_change\\\":1,\\\"total_cdf\\\":5325600}\",\"details\":[{\"libelle\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\",\"formule\":\"190 000,00 × 28,00 CDF\",\"montant_source\":5320000,\"montant_cdf\":5320000,\"devise\":\"CDF\"},{\"libelle\":\"Frais administratif\",\"formule\":\"2 800,00 CDF\",\"montant_source\":2800,\"montant_cdf\":2800,\"devise\":\"CDF\"},{\"libelle\":\"Frais technique\",\"formule\":\"2 800,00 CDF\",\"montant_source\":2800,\"montant_cdf\":2800,\"devise\":\"CDF\"}]},\"details\":[{\"libelle\":\"Taxe de mise sur le marché des matières non\\r\\nbiodégradables (cartes prép. Mèches)\",\"formule\":\"190 000,00 × 28,00 CDF\",\"montant_source\":5320000,\"montant_cdf\":5320000,\"devise\":\"CDF\"},{\"libelle\":\"Frais administratif\",\"formule\":\"2 800,00 CDF\",\"montant_source\":2800,\"montant_cdf\":2800,\"devise\":\"CDF\"},{\"libelle\":\"Frais technique\",\"formule\":\"2 800,00 CDF\",\"montant_source\":2800,\"montant_cdf\":2800,\"devise\":\"CDF\"}],\"periode\":{\"code\":\"ponctuelle\",\"libelle\":\"Ponctuelle\",\"mois_concernes\":0,\"mois_liste\":\"\"}}', 0.00, 0.00, 0.00, 0.00, 190000.000000, 190000.000000, 5320000.00, 2800.00, 2800.00, 5325600.00, 'CDF', 1.0000, 5325600.00, 5325600.00, NULL, NULL, NULL, NULL, 'ponctuelle', 'par_unite', NULL, 0.00, 0.00, 'Ponctuelle'),
(61, 62, 1, 1, 'Impôts sur les revenus locatifs (IRL)', 'irl', 'm1', 'Janvier', '1', '{\"engine\":\"FiscalEngineRefonte\",\"article\":{\"id\":1,\"code_article\":\"17111600\",\"nature_acte\":\"Impôts sur les revenus locatifs (IRL)\",\"libelle_taux\":\"a) Personne physique non commerçante\"},\"calcul\":{\"type_calcul\":\"irl\",\"type_personne\":\"personne_physique_non_commercante\",\"periode_code\":\"m1\",\"periode_libelle\":\"Janvier\",\"mois_concernes\":1,\"mois_liste\":\"Janvier\",\"mois_texte\":\"Janvier\",\"loyer_mensuel\":900000,\"base\":900000,\"base_imposable\":900000,\"quantite\":1,\"taux\":10,\"taux_pourcentage\":10,\"principal_source\":90000,\"frais_admin_source\":0,\"frais_tech_source\":0,\"total_source\":90000,\"montant_acte_cdf\":90000,\"montant_frais_admin_cdf\":0,\"montant_frais_tech_cdf\":0,\"total_ligne_cdf\":90000,\"devise_source\":\"CDF\",\"taux_change\":1,\"details_calcul\":\"{\\\"details\\\":[{\\\"libelle\\\":\\\"Impôts sur les revenus locatifs (IRL)\\\",\\\"formule\\\":\\\"900 000,00 CDF × 10%\\\",\\\"montant_source\\\":90000,\\\"montant_cdf\\\":90000,\\\"devise\\\":\\\"CDF\\\",\\\"mois\\\":\\\"Janvier\\\"}],\\\"periode\\\":{\\\"code\\\":\\\"m1\\\",\\\"libelle\\\":\\\"Janvier\\\",\\\"nombre_mois\\\":1,\\\"mois_nums\\\":[1],\\\"mois_liste\\\":\\\"Janvier\\\",\\\"mois_texte\\\":\\\"Janvier\\\"},\\\"devise_source\\\":\\\"CDF\\\",\\\"taux_change\\\":1,\\\"total_cdf\\\":90000}\",\"details\":[{\"libelle\":\"Impôts sur les revenus locatifs (IRL)\",\"formule\":\"900 000,00 CDF × 10%\",\"montant_source\":90000,\"montant_cdf\":90000,\"devise\":\"CDF\",\"mois\":\"Janvier\"}]},\"details\":[{\"libelle\":\"Impôts sur les revenus locatifs (IRL)\",\"formule\":\"900 000,00 CDF × 10%\",\"montant_source\":90000,\"montant_cdf\":90000,\"devise\":\"CDF\",\"mois\":\"Janvier\"}],\"periode\":{\"code\":\"m1\",\"libelle\":\"Janvier\",\"mois_concernes\":1,\"mois_liste\":\"Janvier\"}}', 900000.00, 10.00, 0.00, 10.00, 900000.000000, 1.000000, 90000.00, 0.00, 0.00, 90000.00, 'CDF', 1.0000, 90000.00, 90000.00, NULL, NULL, NULL, NULL, 'annuelle', 'irl', NULL, 0.00, 0.00, 'Janvier'),
(62, 63, 1, 1, 'Impôts sur les revenus locatifs (IRL)', 'irl', 't1', 'Premier trimestre', '3', '{\"engine\":\"FiscalEngineRefonte\",\"article\":{\"id\":1,\"code_article\":\"17111600\",\"nature_acte\":\"Impôts sur les revenus locatifs (IRL)\",\"libelle_taux\":\"a) Personne physique non commerçante\"},\"calcul\":{\"type_calcul\":\"irl\",\"type_personne\":\"personne_physique_non_commercante\",\"periode_code\":\"t1\",\"periode_libelle\":\"Premier trimestre\",\"mois_concernes\":3,\"mois_liste\":\"Janvier, Février, Mars\",\"mois_texte\":\"Janvier, Février, Mars\",\"loyer_mensuel\":89000090,\"base\":267000270,\"base_imposable\":267000270,\"quantite\":1,\"taux\":10,\"taux_pourcentage\":10,\"principal_source\":26700027,\"frais_admin_source\":0,\"frais_tech_source\":0,\"total_source\":26700027,\"montant_acte_cdf\":26700027,\"montant_frais_admin_cdf\":0,\"montant_frais_tech_cdf\":0,\"total_ligne_cdf\":26700027,\"devise_source\":\"CDF\",\"taux_change\":1,\"details_calcul\":\"{\\\"details\\\":[{\\\"libelle\\\":\\\"Impôts sur les revenus locatifs (IRL)\\\",\\\"formule\\\":\\\"267 000 270,00 CDF × 10%\\\",\\\"montant_source\\\":26700027,\\\"montant_cdf\\\":26700027,\\\"devise\\\":\\\"CDF\\\",\\\"mois\\\":\\\"Janvier, Février, Mars\\\"}],\\\"periode\\\":{\\\"code\\\":\\\"t1\\\",\\\"libelle\\\":\\\"Premier trimestre\\\",\\\"nombre_mois\\\":3,\\\"mois_nums\\\":[1,2,3],\\\"mois_liste\\\":\\\"Janvier, Février, Mars\\\",\\\"mois_texte\\\":\\\"Janvier, Février, Mars\\\"},\\\"devise_source\\\":\\\"CDF\\\",\\\"taux_change\\\":1,\\\"total_cdf\\\":26700027}\",\"details\":[{\"libelle\":\"Impôts sur les revenus locatifs (IRL)\",\"formule\":\"267 000 270,00 CDF × 10%\",\"montant_source\":26700027,\"montant_cdf\":26700027,\"devise\":\"CDF\",\"mois\":\"Janvier, Février, Mars\"}]},\"details\":[{\"libelle\":\"Impôts sur les revenus locatifs (IRL)\",\"formule\":\"267 000 270,00 CDF × 10%\",\"montant_source\":26700027,\"montant_cdf\":26700027,\"devise\":\"CDF\",\"mois\":\"Janvier, Février, Mars\"}],\"periode\":{\"code\":\"t1\",\"libelle\":\"Premier trimestre\",\"mois_concernes\":3,\"mois_liste\":\"Janvier, Février, Mars\"}}', 89000090.00, 10.00, 0.00, 10.00, 267000270.000000, 1.000000, 26700027.00, 0.00, 0.00, 26700027.00, 'CDF', 1.0000, 26700027.00, 26700027.00, NULL, NULL, NULL, NULL, 'annuelle', 'irl', NULL, 0.00, 0.00, 'Premier trimestre');

--
-- Déclencheurs `notes_taxation_details`
--
DELIMITER $$
CREATE TRIGGER `prevent_update_nt_details` BEFORE UPDATE ON `notes_taxation_details` FOR EACH ROW BEGIN
    DECLARE statut_nt VARCHAR(50);

    SELECT statut INTO statut_nt
    FROM notes_taxation
    WHERE id = OLD.note_taxation_id;

    IF statut_nt <> 'brouillon' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Modification interdite : détails NT déjà liquidée';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `recalc_total_nt_delete` AFTER DELETE ON `notes_taxation_details` FOR EACH ROW BEGIN
    UPDATE notes_taxation
    SET total_estime = (
        SELECT IFNULL(SUM(total_ligne_cdf),0)
        FROM notes_taxation_details
        WHERE note_taxation_id = OLD.note_taxation_id
    )
    + IFNULL(penalite_assiette,0)
    + IFNULL(penalite_recouvrement,0)
    WHERE id = OLD.note_taxation_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `recalc_total_nt_insert` AFTER INSERT ON `notes_taxation_details` FOR EACH ROW BEGIN
    UPDATE notes_taxation
    SET total_estime = (
        SELECT IFNULL(SUM(total_ligne_cdf),0)
        FROM notes_taxation_details
        WHERE note_taxation_id = NEW.note_taxation_id
    )
    + IFNULL(penalite_assiette,0)
    + IFNULL(penalite_recouvrement,0)
    WHERE id = NEW.note_taxation_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `recalc_total_nt_update` AFTER UPDATE ON `notes_taxation_details` FOR EACH ROW BEGIN
    UPDATE notes_taxation
    SET total_estime = (
        SELECT IFNULL(SUM(total_ligne_cdf),0)
        FROM notes_taxation_details
        WHERE note_taxation_id = NEW.note_taxation_id
    )
    + IFNULL(penalite_assiette,0)
    + IFNULL(penalite_recouvrement,0)
    WHERE id = NEW.note_taxation_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_nt_recalculate` AFTER INSERT ON `notes_taxation_details` FOR EACH ROW BEGIN

UPDATE notes_taxation
SET
montant_acte_total =
(
SELECT IFNULL(SUM(montant_acte),0)
FROM notes_taxation_details
WHERE note_taxation_id = NEW.note_taxation_id
),

montant_frais_admin_total =
(
SELECT IFNULL(SUM(montant_frais_admin),0)
FROM notes_taxation_details
WHERE note_taxation_id = NEW.note_taxation_id
),

montant_frais_tech_total =
(
SELECT IFNULL(SUM(montant_frais_tech),0)
FROM notes_taxation_details
WHERE note_taxation_id = NEW.note_taxation_id
),

total_estime =
(
SELECT IFNULL(SUM(total_ligne),0)
FROM notes_taxation_details
WHERE note_taxation_id = NEW.note_taxation_id
)

WHERE id = NEW.note_taxation_id;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `note_banques`
--

CREATE TABLE `note_banques` (
  `id` int(11) NOT NULL,
  `note_perception_id` int(11) NOT NULL,
  `compte_bancaire_id` int(11) NOT NULL,
  `montant_affecte` decimal(18,2) DEFAULT 0.00,
  `observation` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `note_banques`
--

INSERT INTO `note_banques` (`id`, `note_perception_id`, `compte_bancaire_id`, `montant_affecte`, `observation`, `created_at`) VALUES
(1, 1, 5, 3178000.00, 'Affectation bancaire à la création de la NP', '2026-06-15 13:28:16'),
(2, 2, 2, 266000000.00, 'Affectation bancaire à la création de la NP', '2026-06-15 14:00:24'),
(3, 3, 4, 21258000.00, 'Affectation bancaire à la création de la NP', '2026-06-15 14:51:47'),
(4, 4, 5, 720000.00, 'Affectation bancaire à la création de la NP', '2026-06-26 17:45:46'),
(5, 7, 5, 5325600.00, 'Affectation bancaire à la création de la NP', '2026-06-27 00:20:46'),
(6, 8, 5, 386400.00, 'Affectation bancaire à la création de la NP', '2026-06-27 16:04:05'),
(7, 9, 5, 26700027.00, 'Affectation bancaire à la création de la NP', '2026-06-27 17:46:20'),
(8, 10, 4, 378112000.00, 'Affectation bancaire à la création de la NP', '2026-06-29 16:31:47');

-- --------------------------------------------------------

--
-- Structure de la table `paiements`
--

CREATE TABLE `paiements` (
  `id` int(11) NOT NULL,
  `note_perception_id` int(11) DEFAULT NULL,
  `fraction_id` int(11) DEFAULT NULL,
  `date_paiement` date NOT NULL,
  `montant_paye` decimal(18,2) NOT NULL,
  `devise` enum('CDF','USD','EUR') NOT NULL DEFAULT 'CDF',
  `taux_change` decimal(18,4) NOT NULL DEFAULT 1.0000,
  `montant_converti_cdf` decimal(18,2) NOT NULL,
  `mode_paiement_id` int(11) NOT NULL,
  `banque` varchar(150) DEFAULT NULL,
  `numero_compte` varchar(150) DEFAULT NULL,
  `intitule_compte` varchar(200) DEFAULT NULL,
  `reference_transaction` varchar(150) DEFAULT NULL,
  `compte_credite` varchar(150) DEFAULT NULL,
  `type_carte` varchar(80) DEFAULT NULL,
  `banque_emettrice` varchar(150) DEFAULT NULL,
  `numero_carte_masque` varchar(50) DEFAULT NULL,
  `banque_beneficiaire` varchar(150) DEFAULT NULL,
  `reseau_mobile_money` varchar(80) DEFAULT NULL,
  `telephone_mobile_money` varchar(30) DEFAULT NULL,
  `titulaire_mobile_money` varchar(150) DEFAULT NULL,
  `justificatif` varchar(255) DEFAULT NULL,
  `observation` text DEFAULT NULL,
  `statut` enum('enregistre','apure_partiel','apure_total','annule') DEFAULT 'enregistre',
  `user_comptable_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `paiements`
--

INSERT INTO `paiements` (`id`, `note_perception_id`, `fraction_id`, `date_paiement`, `montant_paye`, `devise`, `taux_change`, `montant_converti_cdf`, `mode_paiement_id`, `banque`, `numero_compte`, `intitule_compte`, `reference_transaction`, `compte_credite`, `type_carte`, `banque_emettrice`, `numero_carte_masque`, `banque_beneficiaire`, `reseau_mobile_money`, `telephone_mobile_money`, `titulaire_mobile_money`, `justificatif`, `observation`, `statut`, `user_comptable_id`, `created_at`) VALUES
(1, 2, NULL, '2026-06-15', 266000000.00, 'CDF', 1.0000, 266000000.00, 1, 'Rawbank', '000-RAW-CDF-001', 'Compte recettes provinciales', 'BV0001-1', '000-RAW-CDF-001', '', '', '', '', '', '', '', NULL, 'RAS', 'apure_total', 3, '2026-06-15 14:01:19'),
(2, 1, NULL, '2026-06-15', 3178000.00, 'CDF', 1.0000, 3178000.00, 1, 'Ecobank', '000-ECO-CDF-001', 'Compte recettes provinciales', 'BV0001-1', '000-ECO-CDF-001', '', '', '', '', '', '', '', NULL, '', 'apure_total', 3, '2026-06-15 14:29:42'),
(3, 3, NULL, '2026-06-15', 21258000.00, 'CDF', 1.0000, 21258000.00, 1, 'Equity BCDC', '000-EQB-CDF-001', 'Compte recettes provinciales', 'BV00014', '000-EQB-CDF-001', '', '', '', '', '', '', '', NULL, '', 'apure_total', 3, '2026-06-15 15:13:56'),
(4, 5, NULL, '2026-06-26', 500000.00, 'CDF', 1.0000, 500000.00, 1, 'Ecobank', '000-ECO-CDF-001', 'Compte recettes provinciales', 'BV0001-1', '000-ECO-CDF-001', '', '', '', '', '', '', '', NULL, 'VALIDE', 'apure_total', 3, '2026-06-26 17:48:20'),
(5, 6, NULL, '2026-06-26', 220000.00, 'CDF', 1.0000, 220000.00, 1, 'Ecobank', '000-ECO-CDF-001', 'Compte recettes provinciales', 'BV0001-2', '000-ECO-CDF-001', '', '', '', '', 'M-Pesa', '', '', NULL, 'RAS', 'apure_total', 3, '2026-06-26 17:50:22'),
(6, 8, NULL, '2026-06-27', 386400.00, 'CDF', 1.0000, 386400.00, 1, 'Ecobank', '000-ECO-CDF-001', 'Compte recettes provinciales', 'BV0001-1', '000-ECO-CDF-001', '', '', '', '', '', '', '', NULL, '', 'apure_total', 20, '2026-06-27 16:21:02'),
(7, 9, NULL, '2026-06-27', 26700027.00, 'CDF', 1.0000, 26700027.00, 1, 'Ecobank', '000-ECO-CDF-001', 'Compte recettes provinciales', 'BV00014', '000-ECO-CDF-001', '', '', '', '', '', '', '', NULL, '', 'apure_total', 3, '2026-06-27 17:46:46');

-- --------------------------------------------------------

--
-- Structure de la table `parametres_penalites_progressives`
--

CREATE TABLE `parametres_penalites_progressives` (
  `id` int(11) NOT NULL,
  `type` enum('assiette','recouvrement') NOT NULL,
  `tranche_debut` int(11) NOT NULL,
  `tranche_fin` int(11) NOT NULL,
  `taux_pourcentage` decimal(5,2) NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `parametres_penalites_progressives`
--

INSERT INTO `parametres_penalites_progressives` (`id`, `type`, `tranche_debut`, `tranche_fin`, `taux_pourcentage`, `actif`, `created_at`) VALUES
(1, 'recouvrement', 1, 30, 10.00, 1, '2026-05-31 19:42:37'),
(2, 'recouvrement', 31, 60, 20.00, 1, '2026-05-31 19:42:37'),
(3, 'recouvrement', 61, 9999, 30.00, 1, '2026-05-31 19:42:37'),
(4, 'assiette', 1, 30, 5.00, 1, '2026-05-31 19:42:37'),
(5, 'assiette', 31, 60, 10.00, 1, '2026-05-31 19:42:37'),
(6, 'assiette', 61, 9999, 15.00, 1, '2026-05-31 19:42:37');

-- --------------------------------------------------------

--
-- Structure de la table `penalites_historique`
--

CREATE TABLE `penalites_historique` (
  `id` int(11) NOT NULL,
  `type` enum('assiette','recouvrement') NOT NULL,
  `reference_type` enum('ND','NP','FRACTION') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `montant_base` decimal(18,2) NOT NULL,
  `taux_applique` decimal(5,2) NOT NULL,
  `montant_penalite` decimal(18,2) NOT NULL,
  `jours_retard` int(11) DEFAULT 0,
  `date_application` date NOT NULL,
  `statut` enum('proposee','validee','suspendue','annulee') DEFAULT 'proposee',
  `justification` text DEFAULT NULL,
  `signature_hash` text DEFAULT NULL,
  `user_validation_id` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déclencheurs `penalites_historique`
--
DELIMITER $$
CREATE TRIGGER `prevent_update_penalite_validee` BEFORE UPDATE ON `penalites_historique` FOR EACH ROW BEGIN
    IF OLD.statut = 'validee' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Modification interdite : pénalité déjà validée et signée';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `periodes_taxation`
--

CREATE TABLE `periodes_taxation` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `nombre_mois` int(11) NOT NULL,
  `mois` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `periodes_taxation`
--

INSERT INTO `periodes_taxation` (`id`, `code`, `libelle`, `nombre_mois`, `mois`) VALUES
(1, 'mensuel', 'Mensuel', 1, 'Mois sélectionné'),
(2, 'trimestriel', 'Trimestriel', 3, '3 mois sélectionnés'),
(3, 'semestre_1', 'Premier semestre', 6, 'Janvier,Février,Mars,Avril,Mai,Juin'),
(4, 'semestre_2', 'Deuxième semestre', 6, 'Juillet,Août,Septembre,Octobre,Novembre,Décembre'),
(5, 'annuel', 'Annuel', 12, 'Janvier,Février,Mars,Avril,Mai,Juin,Juillet,Août,Septembre,Octobre,Novembre,Décembre');

-- --------------------------------------------------------

--
-- Structure de la table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `module` varchar(100) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `autorise` tinyint(1) DEFAULT 1,
  `ordre` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `permissions`
--

INSERT INTO `permissions` (`id`, `role_id`, `module`, `action`, `description`, `autorise`, `ordre`, `created_at`, `updated_at`) VALUES
(1, 14, 'apurement', 'creer', NULL, 1, 0, '2026-06-12 14:46:43', NULL),
(2, 14, 'apurement', 'voir', NULL, 1, 0, '2026-06-12 14:47:16', NULL),
(3, 14, 'paiement', 'voir', NULL, 1, 0, '2026-06-12 14:56:16', NULL),
(4, 14, 'paiement', 'creer', NULL, 1, 0, '2026-06-12 14:56:29', NULL),
(5, 14, 'paiement', 'valider', NULL, 1, 0, '2026-06-12 14:56:39', NULL),
(6, 7, 'CORRECTIONS', 'ACCESS', NULL, 1, 0, '2026-06-15 16:49:23', NULL),
(7, 1, 'CORRECTIONS', 'ACCESS', NULL, 1, 0, '2026-06-15 16:49:23', NULL),
(10, 2, 'CORRECTIONS', 'ACCESS', NULL, 1, 0, '2026-06-15 17:06:53', NULL),
(11, 3, 'CORRECTIONS', 'ACCESS', NULL, 1, 0, '2026-06-15 17:06:53', NULL),
(12, 4, 'CORRECTIONS', 'ACCESS', NULL, 1, 0, '2026-06-15 17:06:53', NULL),
(13, 1, 'dashboard', 'view', 'Voir le tableau de bord', 1, 1, '2026-06-22 07:19:44', NULL),
(14, 1, 'users', 'view', 'Voir les utilisateurs', 1, 1, '2026-06-22 07:19:44', NULL),
(15, 1, 'users', 'add', 'Ajouter un utilisateur', 1, 2, '2026-06-22 07:19:44', NULL),
(16, 1, 'users', 'edit', 'Modifier un utilisateur', 1, 3, '2026-06-22 07:19:44', NULL),
(17, 1, 'users', 'delete', 'Supprimer un utilisateur', 1, 4, '2026-06-22 07:19:44', NULL),
(18, 1, 'roles', 'view', 'Voir les rôles', 1, 1, '2026-06-22 07:19:44', NULL),
(19, 1, 'roles', 'add', 'Ajouter un rôle', 1, 2, '2026-06-22 07:19:44', NULL),
(20, 1, 'roles', 'edit', 'Modifier un rôle', 1, 3, '2026-06-22 07:19:44', NULL),
(21, 1, 'roles', 'delete', 'Supprimer un rôle', 1, 4, '2026-06-22 07:19:44', NULL),
(22, 1, 'roles', 'permissions', 'Affecter les permissions', 1, 5, '2026-06-22 07:19:44', NULL),
(23, 1, 'contribuables', 'view', 'Voir', 1, 1, '2026-06-22 07:19:44', NULL),
(24, 1, 'contribuables', 'add', 'Ajouter', 1, 2, '2026-06-22 07:19:45', NULL),
(25, 1, 'contribuables', 'edit', 'Modifier', 1, 3, '2026-06-22 07:19:45', NULL),
(26, 1, 'contribuables', 'delete', 'Supprimer', 1, 4, '2026-06-22 07:19:45', NULL),
(27, 1, 'constatation', 'view', 'Voir les NT', 1, 1, '2026-06-22 07:19:45', NULL),
(28, 1, 'constatation', 'add', 'Créer NT', 1, 2, '2026-06-22 07:19:45', NULL),
(29, 1, 'constatation', 'edit', 'Modifier NT', 1, 3, '2026-06-22 07:19:45', NULL),
(30, 1, 'constatation', 'delete', 'Supprimer NT', 1, 4, '2026-06-22 07:19:45', NULL),
(31, 1, 'constatation', 'print', 'Imprimer NT', 1, 5, '2026-06-22 07:19:45', NULL),
(32, 1, 'constatation', 'send_liquidation', 'Envoyer à la liquidation', 1, 6, '2026-06-22 07:19:45', NULL),
(33, 1, 'liquidation', 'view', 'Voir ND', 1, 1, '2026-06-22 07:19:45', NULL),
(34, 1, 'liquidation', 'add', 'Créer ND', 1, 2, '2026-06-22 07:19:45', NULL),
(35, 1, 'liquidation', 'edit', 'Modifier ND', 1, 3, '2026-06-22 07:19:45', NULL),
(36, 1, 'liquidation', 'validate', 'Valider liquidation', 1, 4, '2026-06-22 07:19:45', NULL),
(37, 1, 'liquidation', 'print', 'Imprimer ND', 1, 5, '2026-06-22 07:19:45', NULL),
(38, 1, 'ordonnancement', 'view', 'Voir NP', 1, 1, '2026-06-22 07:19:45', NULL),
(39, 1, 'ordonnancement', 'create_np', 'Créer NP', 1, 2, '2026-06-22 07:19:45', NULL),
(40, 1, 'ordonnancement', 'edit_np', 'Modifier NP', 1, 3, '2026-06-22 07:19:45', NULL),
(41, 1, 'ordonnancement', 'print_np', 'Imprimer NP', 1, 4, '2026-06-22 07:19:45', NULL),
(42, 1, 'ordonnancement', 'fractionner', 'Fractionner NP', 1, 5, '2026-06-22 07:19:45', NULL),
(43, 1, 'recouvrement', 'view', 'Voir recouvrement', 1, 1, '2026-06-22 07:19:45', NULL),
(44, 1, 'recouvrement', 'apurement', 'Apurement', 1, 2, '2026-06-22 07:19:46', NULL),
(45, 1, 'recouvrement', 'paiement', 'Paiement', 1, 3, '2026-06-22 07:19:46', NULL),
(46, 1, 'recouvrement', 'quittance', 'Quittance', 1, 4, '2026-06-22 07:19:46', NULL),
(47, 1, 'recouvrement', 'print_quittance', 'Imprimer quittance', 1, 5, '2026-06-22 07:19:46', NULL),
(48, 1, 'settings', 'view', 'Voir paramétrage', 1, 1, '2026-06-22 07:19:46', NULL),
(49, 1, 'settings', 'provinces', 'Provinces', 1, 2, '2026-06-22 07:19:46', NULL),
(50, 1, 'settings', 'centres', 'Centres', 1, 3, '2026-06-22 07:19:46', NULL),
(51, 1, 'settings', 'services', 'Services d’assiette', 1, 4, '2026-06-22 07:19:46', NULL),
(52, 1, 'settings', 'articles', 'Articles budgétaires', 1, 5, '2026-06-22 07:19:46', NULL),
(53, 1, 'settings', 'actes', 'Actes taxables', 1, 6, '2026-06-22 07:19:46', NULL),
(54, 1, 'settings', 'taux', 'Taux de change', 1, 7, '2026-06-22 07:19:46', NULL),
(55, 1, 'settings', 'comptes', 'Comptes bancaires', 1, 8, '2026-06-22 07:19:46', NULL),
(56, 1, 'reports', 'view', 'Voir rapports', 1, 1, '2026-06-22 07:19:46', NULL),
(57, 1, 'reports', 'daily', 'Rapport journalier', 1, 2, '2026-06-22 07:19:46', NULL),
(58, 1, 'reports', 'monthly', 'Rapport mensuel', 1, 3, '2026-06-22 07:19:46', NULL),
(59, 1, 'reports', 'export_pdf', 'Export PDF', 1, 4, '2026-06-22 07:19:46', NULL),
(60, 1, 'reports', 'export_excel', 'Export Excel', 1, 5, '2026-06-22 07:19:46', NULL),
(61, 16, 'dashboard', 'view', 'Voir le tableau de bord', 1, 1, '2026-06-22 07:32:49', NULL),
(62, 16, 'contribuables', 'view', 'Voir les contribuables', 1, 10, '2026-06-22 07:32:49', NULL),
(63, 16, 'contribuables', 'add', 'Créer un contribuable', 1, 11, '2026-06-22 07:32:49', NULL),
(64, 16, 'contribuables', 'edit', 'Modifier un contribuable', 1, 12, '2026-06-22 07:32:49', NULL),
(65, 16, 'contribuables', 'delete', 'Supprimer un contribuable', 1, 13, '2026-06-22 07:32:49', NULL),
(66, 16, 'constatation', 'view', 'Voir les notes de taxation', 1, 20, '2026-06-22 07:32:49', NULL),
(67, 16, 'constatation', 'add', 'Créer une note de taxation', 1, 21, '2026-06-22 07:32:49', NULL),
(68, 16, 'constatation', 'edit', 'Modifier une note de taxation', 1, 22, '2026-06-22 07:32:49', NULL),
(69, 16, 'constatation', 'delete', 'Supprimer une note de taxation', 1, 23, '2026-06-22 07:32:49', NULL),
(70, 16, 'constatation', 'detail_add', 'Ajouter les détails de taxation', 1, 24, '2026-06-22 07:32:49', NULL),
(71, 1, 'constatation', 'detail_add', 'Ajouter les détails de taxation', 1, 24, '2026-06-22 07:32:49', NULL),
(72, 16, 'constatation', 'detail_remove', 'Retirer les détails de taxation', 1, 25, '2026-06-22 07:32:49', NULL),
(73, 1, 'constatation', 'detail_remove', 'Retirer les détails de taxation', 1, 25, '2026-06-22 07:32:49', NULL),
(74, 16, 'constatation', 'penalite_update', 'Modifier pénalités sur NT', 1, 26, '2026-06-22 07:32:49', NULL),
(75, 1, 'constatation', 'penalite_update', 'Modifier pénalités sur NT', 1, 26, '2026-06-22 07:32:49', NULL),
(76, 16, 'constatation', 'print', 'Imprimer une note de taxation', 1, 27, '2026-06-22 07:32:49', NULL),
(77, 16, 'constatation', 'send_liquidation', 'Envoyer à la liquidation', 1, 28, '2026-06-22 07:32:49', NULL),
(78, 16, 'liquidation', 'view', 'Voir les notes de débit', 1, 30, '2026-06-22 07:32:49', NULL),
(79, 16, 'liquidation', 'nt_a_liquider', 'Voir les NT à liquider', 1, 31, '2026-06-22 07:32:49', NULL),
(80, 1, 'liquidation', 'nt_a_liquider', 'Voir les NT à liquider', 1, 31, '2026-06-22 07:32:49', NULL),
(81, 16, 'liquidation', 'add', 'Créer une note de débit', 1, 32, '2026-06-22 07:32:49', NULL),
(82, 16, 'liquidation', 'edit', 'Modifier une note de débit', 1, 33, '2026-06-22 07:32:49', NULL),
(83, 16, 'liquidation', 'validate', 'Valider une liquidation', 1, 34, '2026-06-22 07:32:49', NULL),
(84, 16, 'liquidation', 'print', 'Imprimer une note de débit', 1, 35, '2026-06-22 07:32:49', NULL),
(85, 16, 'controle', 'view', 'Voir le contrôle', 1, 40, '2026-06-22 07:32:49', NULL),
(86, 1, 'controle', 'view', 'Voir le contrôle', 1, 40, '2026-06-22 07:32:49', NULL),
(87, 16, 'controle', 'validate_nd', 'Valider les notes de débit', 1, 41, '2026-06-22 07:32:49', NULL),
(88, 1, 'controle', 'validate_nd', 'Valider les notes de débit', 1, 41, '2026-06-22 07:32:49', NULL),
(89, 16, 'controle', 'document_check', 'Contrôler les documents', 1, 42, '2026-06-22 07:32:49', NULL),
(90, 1, 'controle', 'document_check', 'Contrôler les documents', 1, 42, '2026-06-22 07:32:49', NULL),
(91, 16, 'ordonnancement', 'view', 'Voir les notes de perception', 1, 50, '2026-06-22 07:32:49', NULL),
(92, 16, 'ordonnancement', 'create_np', 'Créer NP', 1, 51, '2026-06-22 07:32:49', NULL),
(93, 16, 'ordonnancement', 'edit_np', 'Modifier NP', 1, 52, '2026-06-22 07:32:49', NULL),
(94, 16, 'ordonnancement', 'print_np', 'Imprimer NP', 1, 53, '2026-06-22 07:32:49', NULL),
(95, 16, 'ordonnancement', 'create_npf', 'Créer NPF', 1, 54, '2026-06-22 07:32:49', NULL),
(96, 1, 'ordonnancement', 'create_npf', 'Créer NPF', 1, 54, '2026-06-22 07:32:49', NULL),
(97, 16, 'ordonnancement', 'view_npf', 'Voir NPF', 1, 55, '2026-06-22 07:32:49', NULL),
(98, 1, 'ordonnancement', 'view_npf', 'Voir NPF', 1, 55, '2026-06-22 07:32:49', NULL),
(99, 16, 'ordonnancement', 'fractionner', 'Fractionner NP', 1, 56, '2026-06-22 07:32:49', NULL),
(100, 16, 'ordonnancement', 'view_fractions', 'Voir fractions', 1, 57, '2026-06-22 07:32:49', NULL),
(101, 1, 'ordonnancement', 'view_fractions', 'Voir fractions', 1, 57, '2026-06-22 07:32:49', NULL),
(102, 16, 'ordonnancement', 'avis_fractionnement', 'Créer avis fractionnement', 1, 58, '2026-06-22 07:32:49', NULL),
(103, 1, 'ordonnancement', 'avis_fractionnement', 'Créer avis fractionnement', 1, 58, '2026-06-22 07:32:49', NULL),
(104, 16, 'ordonnancement', 'avis_fractionnement_view', 'Voir avis fractionnement', 1, 59, '2026-06-22 07:32:49', NULL),
(105, 1, 'ordonnancement', 'avis_fractionnement_view', 'Voir avis fractionnement', 1, 59, '2026-06-22 07:32:49', NULL),
(106, 16, 'ordonnancement', 'avis_fractionnement_print', 'Imprimer avis fractionnement', 1, 60, '2026-06-22 07:32:49', NULL),
(107, 1, 'ordonnancement', 'avis_fractionnement_print', 'Imprimer avis fractionnement', 1, 60, '2026-06-22 07:32:49', NULL),
(108, 16, 'recouvrement', 'view', 'Voir recouvrement', 1, 70, '2026-06-22 07:32:49', NULL),
(109, 16, 'recouvrement', 'paiement', 'Enregistrer paiement', 1, 71, '2026-06-22 07:32:49', NULL),
(110, 16, 'recouvrement', 'paiement_fraction', 'Paiement fractionné', 1, 72, '2026-06-22 07:32:49', NULL),
(111, 1, 'recouvrement', 'paiement_fraction', 'Paiement fractionné', 1, 72, '2026-06-22 07:32:49', NULL),
(112, 16, 'recouvrement', 'paiement_view', 'Voir paiements', 1, 73, '2026-06-22 07:32:49', NULL),
(113, 1, 'recouvrement', 'paiement_view', 'Voir paiements', 1, 73, '2026-06-22 07:32:49', NULL),
(114, 16, 'recouvrement', 'apurement', 'Faire apurement', 1, 74, '2026-06-22 07:32:49', NULL),
(115, 16, 'recouvrement', 'apurement_view', 'Voir apurements', 1, 75, '2026-06-22 07:32:49', NULL),
(116, 1, 'recouvrement', 'apurement_view', 'Voir apurements', 1, 75, '2026-06-22 07:32:49', NULL),
(117, 16, 'recouvrement', 'quittance', 'Générer quittance', 1, 76, '2026-06-22 07:32:49', NULL),
(118, 16, 'recouvrement', 'quittance_view', 'Voir quittances', 1, 77, '2026-06-22 07:32:49', NULL),
(119, 1, 'recouvrement', 'quittance_view', 'Voir quittances', 1, 77, '2026-06-22 07:32:49', NULL),
(120, 16, 'recouvrement', 'print_quittance', 'Imprimer quittance', 1, 78, '2026-06-22 07:32:49', NULL),
(121, 16, 'amr', 'view', 'Voir AMR', 1, 80, '2026-06-22 07:32:49', NULL),
(122, 1, 'amr', 'view', 'Voir AMR', 1, 80, '2026-06-22 07:32:49', NULL),
(123, 16, 'amr', 'generate', 'Générer AMR', 1, 81, '2026-06-22 07:32:49', NULL),
(124, 1, 'amr', 'generate', 'Générer AMR', 1, 81, '2026-06-22 07:32:49', NULL),
(125, 16, 'amr', 'validate', 'Valider AMR', 1, 82, '2026-06-22 07:32:49', NULL),
(126, 1, 'amr', 'validate', 'Valider AMR', 1, 82, '2026-06-22 07:32:49', NULL),
(127, 16, 'amr', 'print', 'Imprimer AMR', 1, 83, '2026-06-22 07:32:49', NULL),
(128, 1, 'amr', 'print', 'Imprimer AMR', 1, 83, '2026-06-22 07:32:49', NULL),
(129, 16, 'penalites', 'view', 'Voir pénalités', 1, 90, '2026-06-22 07:32:49', NULL),
(130, 1, 'penalites', 'view', 'Voir pénalités', 1, 90, '2026-06-22 07:32:49', NULL),
(131, 16, 'penalites', 'settings', 'Paramétrer pénalités', 1, 91, '2026-06-22 07:32:49', NULL),
(132, 1, 'penalites', 'settings', 'Paramétrer pénalités', 1, 91, '2026-06-22 07:32:49', NULL),
(133, 16, 'penalites', 'apply', 'Appliquer pénalités', 1, 92, '2026-06-22 07:32:49', NULL),
(134, 1, 'penalites', 'apply', 'Appliquer pénalités', 1, 92, '2026-06-22 07:32:49', NULL),
(135, 16, 'penalites', 'validate', 'Valider pénalités', 1, 93, '2026-06-22 07:32:49', NULL),
(136, 1, 'penalites', 'validate', 'Valider pénalités', 1, 93, '2026-06-22 07:32:49', NULL),
(137, 16, 'penalites', 'history', 'Historique pénalités', 1, 94, '2026-06-22 07:32:49', NULL),
(138, 1, 'penalites', 'history', 'Historique pénalités', 1, 94, '2026-06-22 07:32:49', NULL),
(139, 16, 'inspection', 'view', 'Voir inspection', 1, 100, '2026-06-22 07:32:49', NULL),
(140, 1, 'inspection', 'view', 'Voir inspection', 1, 100, '2026-06-22 07:32:49', NULL),
(141, 16, 'inspection', 'dashboard', 'Dashboard inspection', 1, 101, '2026-06-22 07:32:49', NULL),
(142, 1, 'inspection', 'dashboard', 'Dashboard inspection', 1, 101, '2026-06-22 07:32:49', NULL),
(143, 16, 'inspection', 'scan_qr', 'Scanner QR', 1, 102, '2026-06-22 07:32:49', NULL),
(144, 1, 'inspection', 'scan_qr', 'Scanner QR', 1, 102, '2026-06-22 07:32:49', NULL),
(145, 16, 'inspection', 'fraude', 'Fraudes suspectes', 1, 103, '2026-06-22 07:32:49', NULL),
(146, 1, 'inspection', 'fraude', 'Fraudes suspectes', 1, 103, '2026-06-22 07:32:49', NULL),
(147, 16, 'inspection', 'alertes', 'Alertes', 1, 104, '2026-06-22 07:32:49', NULL),
(148, 1, 'inspection', 'alertes', 'Alertes', 1, 104, '2026-06-22 07:32:49', NULL),
(149, 16, 'inspection', 'documents_revoques', 'Documents révoqués', 1, 105, '2026-06-22 07:32:49', NULL),
(150, 1, 'inspection', 'documents_revoques', 'Documents révoqués', 1, 105, '2026-06-22 07:32:49', NULL),
(151, 16, 'corrections', 'view', 'Voir corrections', 1, 110, '2026-06-22 07:32:49', NULL),
(152, 1, 'corrections', 'view', 'Voir corrections', 1, 110, '2026-06-22 07:32:49', NULL),
(153, 16, 'corrections', 'search', 'Rechercher correction', 1, 111, '2026-06-22 07:32:49', NULL),
(154, 1, 'corrections', 'search', 'Rechercher correction', 1, 111, '2026-06-22 07:32:49', NULL),
(155, 16, 'corrections', 'edit', 'Modifier correction', 1, 112, '2026-06-22 07:32:49', NULL),
(156, 1, 'corrections', 'edit', 'Modifier correction', 1, 112, '2026-06-22 07:32:49', NULL),
(157, 16, 'corrections', 'history', 'Historique corrections', 1, 113, '2026-06-22 07:32:49', NULL),
(158, 1, 'corrections', 'history', 'Historique corrections', 1, 113, '2026-06-22 07:32:49', NULL),
(159, 16, 'corrections', 'reports', 'Rapports corrections', 1, 114, '2026-06-22 07:32:49', NULL),
(160, 1, 'corrections', 'reports', 'Rapports corrections', 1, 114, '2026-06-22 07:32:49', NULL),
(161, 16, 'qr', 'view', 'Accéder lecteur QR', 1, 120, '2026-06-22 07:32:49', NULL),
(162, 1, 'qr', 'view', 'Accéder lecteur QR', 1, 120, '2026-06-22 07:32:49', NULL),
(163, 16, 'qr', 'scan', 'Scanner QR', 1, 121, '2026-06-22 07:32:49', NULL),
(164, 1, 'qr', 'scan', 'Scanner QR', 1, 121, '2026-06-22 07:32:49', NULL),
(165, 16, 'qr', 'verify', 'Vérifier document', 1, 122, '2026-06-22 07:32:49', NULL),
(166, 1, 'qr', 'verify', 'Vérifier document', 1, 122, '2026-06-22 07:32:49', NULL),
(167, 16, 'qr', 'verifications', 'Voir vérifications', 1, 123, '2026-06-22 07:32:49', NULL),
(168, 1, 'qr', 'verifications', 'Voir vérifications', 1, 123, '2026-06-22 07:32:49', NULL),
(169, 16, 'pwa', 'view', 'Accéder PWA terrain', 1, 130, '2026-06-22 07:32:49', NULL),
(170, 1, 'pwa', 'view', 'Accéder PWA terrain', 1, 130, '2026-06-22 07:32:49', NULL),
(171, 16, 'pwa', 'dashboard', 'Dashboard PWA', 1, 131, '2026-06-22 07:32:49', NULL),
(172, 1, 'pwa', 'dashboard', 'Dashboard PWA', 1, 131, '2026-06-22 07:32:49', NULL),
(173, 16, 'pwa', 'sync', 'Synchronisation terrain', 1, 132, '2026-06-22 07:32:49', NULL),
(174, 1, 'pwa', 'sync', 'Synchronisation terrain', 1, 132, '2026-06-22 07:32:49', NULL),
(175, 16, 'pwa', 'tickets', 'Tickets terrain', 1, 133, '2026-06-22 07:32:49', NULL),
(176, 1, 'pwa', 'tickets', 'Tickets terrain', 1, 133, '2026-06-22 07:32:49', NULL),
(177, 16, 'pwa', 'rapport_z', 'Rapport Z terrain', 1, 134, '2026-06-22 07:32:49', NULL),
(178, 1, 'pwa', 'rapport_z', 'Rapport Z terrain', 1, 134, '2026-06-22 07:32:49', NULL),
(179, 16, 'pwa', 'agents', 'Agents terrain', 1, 135, '2026-06-22 07:32:49', NULL),
(180, 1, 'pwa', 'agents', 'Agents terrain', 1, 135, '2026-06-22 07:32:49', NULL),
(181, 16, 'pwa', 'backup', 'Sauvegarde terrain', 1, 136, '2026-06-22 07:32:49', NULL),
(182, 1, 'pwa', 'backup', 'Sauvegarde terrain', 1, 136, '2026-06-22 07:32:49', NULL),
(183, 16, 'pwa', 'cartographie', 'Cartographie recettes', 1, 137, '2026-06-22 07:32:49', NULL),
(184, 1, 'pwa', 'cartographie', 'Cartographie recettes', 1, 137, '2026-06-22 07:32:49', NULL),
(185, 16, 'reports', 'view', 'Voir rapports', 1, 140, '2026-06-22 07:32:49', NULL),
(186, 16, 'reports', 'monthly', 'Rapport mensuel', 1, 141, '2026-06-22 07:32:49', NULL),
(187, 16, 'reports', 'nt_pdf', 'PDF NT', 1, 142, '2026-06-22 07:32:49', NULL),
(188, 1, 'reports', 'nt_pdf', 'PDF NT', 1, 142, '2026-06-22 07:32:49', NULL),
(189, 16, 'reports', 'nd_pdf', 'PDF ND', 1, 143, '2026-06-22 07:32:49', NULL),
(190, 1, 'reports', 'nd_pdf', 'PDF ND', 1, 143, '2026-06-22 07:32:49', NULL),
(191, 16, 'reports', 'np_pdf', 'PDF NP', 1, 144, '2026-06-22 07:32:49', NULL),
(192, 1, 'reports', 'np_pdf', 'PDF NP', 1, 144, '2026-06-22 07:32:49', NULL),
(193, 16, 'reports', 'npf_pdf', 'PDF NPF', 1, 145, '2026-06-22 07:32:49', NULL),
(194, 1, 'reports', 'npf_pdf', 'PDF NPF', 1, 145, '2026-06-22 07:32:49', NULL),
(195, 16, 'reports', 'quittance_pdf', 'PDF Quittance', 1, 146, '2026-06-22 07:32:49', NULL),
(196, 1, 'reports', 'quittance_pdf', 'PDF Quittance', 1, 146, '2026-06-22 07:32:49', NULL),
(197, 16, 'reports', 'amr_pdf', 'PDF AMR', 1, 147, '2026-06-22 07:32:49', NULL),
(198, 1, 'reports', 'amr_pdf', 'PDF AMR', 1, 147, '2026-06-22 07:32:49', NULL),
(199, 16, 'reports', 'avis_fractionnement_pdf', 'PDF avis fractionnement', 1, 148, '2026-06-22 07:32:49', NULL),
(200, 1, 'reports', 'avis_fractionnement_pdf', 'PDF avis fractionnement', 1, 148, '2026-06-22 07:32:49', NULL),
(201, 16, 'reports', 'attestation_paiement_pdf', 'PDF attestation paiement', 1, 149, '2026-06-22 07:32:49', NULL),
(202, 1, 'reports', 'attestation_paiement_pdf', 'PDF attestation paiement', 1, 149, '2026-06-22 07:32:49', NULL),
(203, 16, 'settings', 'view', 'Voir paramétrage', 1, 160, '2026-06-22 07:32:49', NULL),
(204, 16, 'settings', 'provinces', 'Provinces', 1, 161, '2026-06-22 07:32:49', NULL),
(205, 16, 'settings', 'centres', 'Centres', 1, 162, '2026-06-22 07:32:49', NULL),
(206, 16, 'settings', 'directions', 'Directions', 1, 163, '2026-06-22 07:32:49', NULL),
(207, 1, 'settings', 'directions', 'Directions', 1, 163, '2026-06-22 07:32:49', NULL),
(208, 16, 'settings', 'services', 'Services', 1, 164, '2026-06-22 07:32:49', NULL),
(209, 16, 'settings', 'articles', 'Nomenclature', 1, 165, '2026-06-22 07:32:49', NULL),
(210, 16, 'settings', 'actes', 'Actes taxables', 1, 166, '2026-06-22 07:32:49', NULL),
(211, 16, 'settings', 'periodicites', 'Périodicités', 1, 167, '2026-06-22 07:32:49', NULL),
(212, 1, 'settings', 'periodicites', 'Périodicités', 1, 167, '2026-06-22 07:32:49', NULL),
(213, 16, 'settings', 'taux', 'Taux change', 1, 168, '2026-06-22 07:32:49', NULL),
(214, 16, 'settings', 'taux_province', 'Taux province', 1, 169, '2026-06-22 07:32:49', NULL),
(215, 1, 'settings', 'taux_province', 'Taux province', 1, 169, '2026-06-22 07:32:49', NULL),
(216, 16, 'settings', 'comptes', 'Comptes bancaires', 1, 170, '2026-06-22 07:32:49', NULL),
(217, 16, 'settings', 'modes_paiement', 'Modes paiement', 1, 171, '2026-06-22 07:32:49', NULL),
(218, 1, 'settings', 'modes_paiement', 'Modes paiement', 1, 171, '2026-06-22 07:32:49', NULL),
(219, 16, 'users', 'view', 'Voir utilisateurs', 1, 180, '2026-06-22 07:32:49', NULL),
(220, 16, 'users', 'add', 'Ajouter utilisateur', 1, 181, '2026-06-22 07:32:49', NULL),
(221, 16, 'users', 'edit', 'Modifier utilisateur', 1, 182, '2026-06-22 07:32:49', NULL),
(222, 16, 'users', 'delete', 'Supprimer utilisateur', 1, 183, '2026-06-22 07:32:49', NULL),
(223, 16, 'users', 'reset_password', 'Réinitialiser mot de passe', 1, 184, '2026-06-22 07:32:49', NULL),
(224, 1, 'users', 'reset_password', 'Réinitialiser mot de passe', 1, 184, '2026-06-22 07:32:49', NULL),
(225, 16, 'users', 'toggle_status', 'Activer/Désactiver utilisateur', 1, 185, '2026-06-22 07:32:49', NULL),
(226, 1, 'users', 'toggle_status', 'Activer/Désactiver utilisateur', 1, 185, '2026-06-22 07:32:49', NULL),
(227, 16, 'roles', 'view', 'Voir rôles', 1, 190, '2026-06-22 07:32:49', NULL),
(228, 16, 'roles', 'add', 'Ajouter rôle', 1, 191, '2026-06-22 07:32:49', NULL),
(229, 16, 'roles', 'edit', 'Modifier rôle', 1, 192, '2026-06-22 07:32:49', NULL),
(230, 16, 'roles', 'delete', 'Supprimer rôle', 1, 193, '2026-06-22 07:32:49', NULL),
(231, 16, 'roles', 'permissions', 'Affecter permissions', 1, 194, '2026-06-22 07:32:49', NULL),
(232, 16, 'administration', 'view', 'Voir administration', 1, 200, '2026-06-22 07:32:49', NULL),
(233, 1, 'administration', 'view', 'Voir administration', 1, 200, '2026-06-22 07:32:49', NULL),
(234, 16, 'administration', 'audit', 'Audit système', 1, 201, '2026-06-22 07:32:49', NULL),
(235, 1, 'administration', 'audit', 'Audit système', 1, 201, '2026-06-22 07:32:49', NULL),
(236, 16, 'administration', 'journaux', 'Journaux', 1, 202, '2026-06-22 07:32:49', NULL),
(237, 1, 'administration', 'journaux', 'Journaux', 1, 202, '2026-06-22 07:32:49', NULL),
(238, 16, 'administration', 'logs', 'Voir logs', 1, 203, '2026-06-22 07:32:49', NULL),
(239, 1, 'administration', 'logs', 'Voir logs', 1, 203, '2026-06-22 07:32:49', NULL),
(240, 16, 'administration', 'permissions_legacy', 'Anciennes permissions', 1, 204, '2026-06-22 07:32:49', NULL),
(241, 1, 'administration', 'permissions_legacy', 'Anciennes permissions', 1, 204, '2026-06-22 07:32:49', NULL),
(242, 14, 'dashboard', 'view', 'Voir le tableau de bord', 1, 1, '2026-06-27 01:52:50', '2026-06-27 19:06:31'),
(243, 14, 'contribuables', 'view', 'Voir contribuables', 0, 1, '2026-06-27 01:52:50', '2026-06-27 19:06:31'),
(244, 14, 'contribuables', 'add', 'Ajouter contribuable', 0, 2, '2026-06-27 01:52:50', '2026-06-27 19:06:31'),
(245, 14, 'contribuables', 'edit', 'Modifier contribuable', 0, 3, '2026-06-27 01:52:50', '2026-06-27 19:06:31'),
(246, 14, 'contribuables', 'delete', 'Supprimer contribuable', 0, 4, '2026-06-27 01:52:50', '2026-06-27 19:06:31'),
(247, 14, 'constatation', 'view', 'Voir NT', 0, 1, '2026-06-27 01:52:50', '2026-06-27 19:06:31'),
(248, 14, 'constatation', 'add', 'Créer NT', 0, 2, '2026-06-27 01:52:50', '2026-06-27 19:06:31'),
(249, 14, 'constatation', 'edit', 'Modifier NT', 0, 3, '2026-06-27 01:52:50', '2026-06-27 19:06:31'),
(250, 14, 'constatation', 'delete', 'Supprimer NT', 0, 4, '2026-06-27 01:52:50', '2026-06-27 19:06:31'),
(251, 14, 'constatation', 'detail_add', 'Ajouter les détails de taxation', 0, 5, '2026-06-27 01:52:50', NULL),
(252, 14, 'constatation', 'detail_remove', 'Retirer les détails de taxation', 0, 6, '2026-06-27 01:52:51', NULL),
(253, 14, 'constatation', 'penalite_update', 'Modifier les pénalités sur NT', 0, 7, '2026-06-27 01:52:51', NULL),
(254, 14, 'constatation', 'print', 'Imprimer NT', 0, 6, '2026-06-27 01:52:51', '2026-06-27 19:06:32'),
(255, 14, 'constatation', 'send_liquidation', 'Envoyer à la liquidation', 0, 9, '2026-06-27 01:52:51', NULL),
(256, 14, 'liquidation', 'view', 'Voir liquidation', 0, 1, '2026-06-27 01:52:51', '2026-06-27 19:06:32'),
(257, 14, 'liquidation', 'nt_a_liquider', 'Voir les NT à liquider', 0, 2, '2026-06-27 01:52:51', NULL),
(258, 14, 'liquidation', 'add', 'Créer une note de débit', 0, 3, '2026-06-27 01:52:51', NULL),
(259, 14, 'liquidation', 'edit', 'Modifier une note de débit', 0, 4, '2026-06-27 01:52:51', NULL),
(260, 14, 'liquidation', 'validate', 'Valider une liquidation', 0, 5, '2026-06-27 01:52:51', NULL),
(261, 14, 'liquidation', 'print', 'Imprimer une note de débit', 0, 6, '2026-06-27 01:52:51', NULL),
(262, 14, 'controle', 'view', 'Voir contrôle', 0, 1, '2026-06-27 01:52:51', '2026-06-27 19:06:32'),
(263, 14, 'controle', 'validate_nd', 'Valider les notes de débit', 0, 2, '2026-06-27 01:52:51', NULL),
(264, 14, 'controle', 'document_check', 'Contrôler les documents', 0, 3, '2026-06-27 01:52:51', NULL),
(265, 14, 'ordonnancement', 'view', 'Voir ordonnancement', 0, 1, '2026-06-27 01:52:51', '2026-06-27 19:06:32'),
(266, 14, 'ordonnancement', 'create_np', 'Créer NP', 0, 2, '2026-06-27 01:52:51', '2026-06-27 19:06:33'),
(267, 14, 'ordonnancement', 'edit_np', 'Modifier une note de perception', 0, 3, '2026-06-27 01:52:51', NULL),
(268, 14, 'ordonnancement', 'print_np', 'Imprimer NP', 0, 5, '2026-06-27 01:52:51', '2026-06-27 19:06:33'),
(269, 14, 'ordonnancement', 'create_npf', 'Créer une note de perception fractionnée', 0, 5, '2026-06-27 01:52:51', NULL),
(270, 14, 'ordonnancement', 'view_npf', 'Voir les NPF', 0, 6, '2026-06-27 01:52:52', NULL),
(271, 14, 'ordonnancement', 'fractionner', 'Fractionner une NP', 0, 7, '2026-06-27 01:52:52', NULL),
(272, 14, 'ordonnancement', 'view_fractions', 'Voir les fractions', 0, 8, '2026-06-27 01:52:52', NULL),
(273, 14, 'ordonnancement', 'avis_fractionnement', 'Créer avis de fractionnement', 0, 4, '2026-06-27 01:52:52', '2026-06-27 19:06:33'),
(274, 14, 'ordonnancement', 'avis_fractionnement_view', 'Voir les avis de fractionnement', 0, 10, '2026-06-27 01:52:52', NULL),
(275, 14, 'ordonnancement', 'avis_fractionnement_print', 'Imprimer un avis de fractionnement', 0, 11, '2026-06-27 01:52:52', NULL),
(276, 14, 'recouvrement', 'view', 'Voir recouvrement', 0, 1, '2026-06-27 01:52:52', '2026-06-27 19:06:33'),
(277, 14, 'recouvrement', 'paiement', 'Enregistrer un paiement', 0, 2, '2026-06-27 01:52:52', NULL),
(278, 14, 'recouvrement', 'paiement_fraction', 'Enregistrer paiement fractionné', 0, 3, '2026-06-27 01:52:52', NULL),
(279, 14, 'recouvrement', 'paiement_view', 'Voir les paiements', 0, 4, '2026-06-27 01:52:52', NULL),
(280, 14, 'recouvrement', 'apurement', 'Apurer NP / NPF', 1, 2, '2026-06-27 01:52:52', '2026-06-27 19:06:33'),
(281, 14, 'recouvrement', 'apurement_view', 'Voir les apurements', 1, 6, '2026-06-27 01:52:52', NULL),
(282, 14, 'recouvrement', 'quittance', 'Générer quittance', 0, 3, '2026-06-27 01:52:52', '2026-06-27 19:06:34'),
(283, 14, 'recouvrement', 'quittance_view', 'Voir les quittances', 0, 8, '2026-06-27 01:52:52', NULL),
(284, 14, 'recouvrement', 'print_quittance', 'Imprimer une quittance', 0, 9, '2026-06-27 01:52:52', NULL),
(285, 14, 'amr', 'view', 'Voir AMR', 1, 1, '2026-06-27 01:52:52', '2026-06-27 19:06:34'),
(286, 14, 'amr', 'generate', 'Générer un AMR', 0, 2, '2026-06-27 01:52:52', NULL),
(287, 14, 'amr', 'validate', 'Valider un AMR', 0, 3, '2026-06-27 01:52:52', NULL),
(288, 14, 'amr', 'print', 'Imprimer AMR', 0, 3, '2026-06-27 01:52:52', '2026-06-27 19:06:34'),
(289, 14, 'penalites', 'view', 'Voir pénalités', 0, 1, '2026-06-27 01:52:53', '2026-06-27 19:06:35'),
(290, 14, 'penalites', 'settings', 'Paramétrer les pénalités', 0, 2, '2026-06-27 01:52:53', NULL),
(291, 14, 'penalites', 'apply', 'Appliquer les pénalités', 0, 3, '2026-06-27 01:52:53', NULL),
(292, 14, 'penalites', 'validate', 'Valider les pénalités', 0, 4, '2026-06-27 01:52:53', NULL),
(293, 14, 'penalites', 'history', 'Voir historique pénalités', 0, 3, '2026-06-27 01:52:53', '2026-06-27 19:06:35'),
(294, 14, 'inspection', 'view', 'Voir inspection', 0, 1, '2026-06-27 01:52:53', '2026-06-27 19:06:35'),
(295, 14, 'inspection', 'dashboard', 'Dashboard inspection', 0, 2, '2026-06-27 01:52:53', NULL),
(296, 14, 'inspection', 'scan_qr', 'Scanner QR code', 0, 3, '2026-06-27 01:52:53', NULL),
(297, 14, 'inspection', 'fraude', 'Fraudes suspectes', 0, 4, '2026-06-27 01:52:53', NULL),
(298, 14, 'inspection', 'alertes', 'Alertes', 0, 5, '2026-06-27 01:52:53', NULL),
(299, 14, 'inspection', 'documents_revoques', 'Documents révoqués', 0, 6, '2026-06-27 01:52:53', NULL),
(300, 14, 'corrections', 'view', 'Voir corrections', 0, 1, '2026-06-27 01:52:53', '2026-06-27 19:06:35'),
(301, 14, 'corrections', 'search', 'Rechercher une correction', 0, 2, '2026-06-27 01:52:53', NULL),
(302, 14, 'corrections', 'edit', 'Modifier une correction', 0, 3, '2026-06-27 01:52:53', NULL),
(303, 14, 'corrections', 'history', 'Historique corrections', 0, 4, '2026-06-27 01:52:53', '2026-06-27 19:06:36'),
(304, 14, 'corrections', 'reports', 'Rapports de corrections', 0, 5, '2026-06-27 01:52:53', NULL),
(305, 14, 'qr', 'view', 'Accéder au lecteur QR', 0, 1, '2026-06-27 01:52:53', NULL),
(306, 14, 'qr', 'scan', 'Scanner un QR code', 0, 2, '2026-06-27 01:52:53', NULL),
(307, 14, 'qr', 'verify', 'Vérifier un document', 0, 3, '2026-06-27 01:52:53', NULL),
(308, 14, 'qr', 'verifications', 'Voir les vérifications', 0, 4, '2026-06-27 01:52:53', NULL),
(309, 14, 'pwa', 'view', 'Voir PWA', 0, 1, '2026-06-27 01:52:53', '2026-06-27 19:06:38'),
(310, 14, 'pwa', 'dashboard', 'Voir dashboard mobile', 0, 2, '2026-06-27 01:52:53', NULL),
(311, 14, 'pwa', 'sync', 'Synchronisation PWA', 0, 2, '2026-06-27 01:52:53', '2026-06-27 19:06:38'),
(312, 14, 'pwa', 'tickets', 'Tickets terrain', 0, 4, '2026-06-27 01:52:54', NULL),
(313, 14, 'pwa', 'rapport_z', 'Rapport Z terrain', 0, 5, '2026-06-27 01:52:54', NULL),
(314, 14, 'pwa', 'agents', 'Agents terrain', 0, 4, '2026-06-27 01:52:54', '2026-06-27 19:06:38'),
(315, 14, 'pwa', 'backup', 'Sauvegarde PWA', 0, 3, '2026-06-27 01:52:54', '2026-06-27 19:06:38'),
(316, 14, 'pwa', 'cartographie', 'Cartographie recettes', 0, 8, '2026-06-27 01:52:54', NULL),
(317, 14, 'reports', 'view', 'Voir les rapports', 0, 1, '2026-06-27 01:52:54', NULL),
(318, 14, 'reports', 'monthly', 'Rapport mensuel', 0, 2, '2026-06-27 01:52:54', NULL),
(319, 14, 'reports', 'nt_pdf', 'Exporter NT PDF', 0, 3, '2026-06-27 01:52:54', NULL),
(320, 14, 'reports', 'nd_pdf', 'Exporter ND PDF', 0, 4, '2026-06-27 01:52:54', NULL),
(321, 14, 'reports', 'np_pdf', 'Exporter NP PDF', 0, 5, '2026-06-27 01:52:54', NULL),
(322, 14, 'reports', 'npf_pdf', 'Exporter NPF PDF', 0, 6, '2026-06-27 01:52:54', NULL),
(323, 14, 'reports', 'quittance_pdf', 'Exporter quittance PDF', 0, 7, '2026-06-27 01:52:54', NULL),
(324, 14, 'reports', 'amr_pdf', 'Exporter AMR PDF', 0, 8, '2026-06-27 01:52:54', NULL),
(325, 14, 'reports', 'avis_fractionnement_pdf', 'Exporter avis fractionnement PDF', 0, 9, '2026-06-27 01:52:54', NULL),
(326, 14, 'reports', 'attestation_paiement_pdf', 'Exporter attestation paiement PDF', 1, 10, '2026-06-27 01:52:54', NULL),
(327, 14, 'settings', 'view', 'Voir le paramétrage', 0, 1, '2026-06-27 01:52:54', NULL),
(328, 14, 'settings', 'provinces', 'Gérer les provinces', 0, 2, '2026-06-27 01:52:54', NULL),
(329, 14, 'settings', 'centres', 'Gérer les centres', 0, 3, '2026-06-27 01:52:54', NULL),
(330, 14, 'settings', 'directions', 'Gérer les directions', 0, 4, '2026-06-27 01:52:54', NULL),
(331, 14, 'settings', 'services', 'Gérer les services d’assiette', 0, 5, '2026-06-27 01:52:54', NULL),
(332, 14, 'settings', 'articles', 'Gérer la nomenclature/articles budgétaires', 0, 6, '2026-06-27 01:52:54', NULL),
(333, 14, 'settings', 'actes', 'Gérer les actes taxables', 0, 7, '2026-06-27 01:52:54', NULL),
(334, 14, 'settings', 'periodicites', 'Gérer les périodicités', 0, 8, '2026-06-27 01:52:54', NULL),
(335, 14, 'settings', 'taux', 'Gérer les taux de change', 0, 9, '2026-06-27 01:52:54', NULL),
(336, 14, 'settings', 'taux_province', 'Gérer les taux par province', 0, 10, '2026-06-27 01:52:55', NULL),
(337, 14, 'settings', 'comptes', 'Gérer les comptes bancaires', 0, 11, '2026-06-27 01:52:55', NULL),
(338, 14, 'settings', 'modes_paiement', 'Gérer les modes de paiement', 0, 12, '2026-06-27 01:52:55', NULL),
(339, 14, 'users', 'view', 'Voir utilisateurs', 0, 1, '2026-06-27 01:52:55', '2026-06-27 19:06:37'),
(340, 14, 'users', 'add', 'Ajouter utilisateur', 0, 2, '2026-06-27 01:52:55', '2026-06-27 19:06:37'),
(341, 14, 'users', 'edit', 'Modifier utilisateur', 0, 3, '2026-06-27 01:52:55', '2026-06-27 19:06:37'),
(342, 14, 'users', 'delete', 'Supprimer utilisateur', 0, 4, '2026-06-27 01:52:55', '2026-06-27 19:06:37'),
(343, 14, 'users', 'reset_password', 'Réinitialiser mot de passe', 0, 5, '2026-06-27 01:52:55', NULL),
(344, 14, 'users', 'toggle_status', 'Activer/Désactiver utilisateur', 0, 6, '2026-06-27 01:52:55', NULL),
(345, 14, 'roles', 'view', 'Voir rôles', 0, 1, '2026-06-27 01:52:55', '2026-06-27 19:06:37'),
(346, 14, 'roles', 'add', 'Ajouter rôle', 0, 2, '2026-06-27 01:52:55', '2026-06-27 19:06:37'),
(347, 14, 'roles', 'edit', 'Modifier rôle', 0, 3, '2026-06-27 01:52:55', '2026-06-27 19:06:37'),
(348, 14, 'roles', 'delete', 'Supprimer rôle', 0, 4, '2026-06-27 01:52:55', '2026-06-27 19:06:38'),
(349, 14, 'roles', 'permissions', 'Gérer permissions', 0, 5, '2026-06-27 01:52:56', '2026-06-27 19:06:38'),
(350, 14, 'administration', 'view', 'Voir administration', 0, 1, '2026-06-27 01:52:56', '2026-06-27 19:06:38'),
(351, 14, 'administration', 'audit', 'Audit système', 0, 2, '2026-06-27 01:52:56', NULL),
(352, 14, 'administration', 'journaux', 'Journaux', 0, 3, '2026-06-27 01:52:56', NULL),
(353, 14, 'administration', 'logs', 'Voir journaux', 0, 2, '2026-06-27 01:52:56', '2026-06-27 19:06:38'),
(354, 14, 'administration', 'permissions_legacy', 'Anciennes permissions', 0, 5, '2026-06-27 01:52:56', NULL),
(355, 14, 'contribuables', 'import', 'Importer contribuables', 0, 5, '2026-06-27 11:34:34', '2026-06-27 19:06:31'),
(356, 14, 'constatation', 'submit', 'Soumettre NT', 0, 5, '2026-06-27 11:34:34', '2026-06-27 19:06:32'),
(357, 14, 'liquidation', 'create_nd', 'Créer ND', 0, 2, '2026-06-27 11:34:34', '2026-06-27 19:06:32'),
(358, 14, 'liquidation', 'validate_nd', 'Valider ND', 0, 3, '2026-06-27 11:34:34', '2026-06-27 19:06:32'),
(359, 14, 'liquidation', 'reject_nd', 'Rejeter ND', 0, 4, '2026-06-27 11:34:35', '2026-06-27 19:06:32'),
(360, 14, 'liquidation', 'print_nd', 'Imprimer ND', 0, 5, '2026-06-27 11:34:35', '2026-06-27 19:06:32'),
(361, 14, 'controle', 'validate', 'Valider contrôle', 0, 2, '2026-06-27 11:34:35', '2026-06-27 19:06:32'),
(362, 14, 'controle', 'reject', 'Rejeter contrôle', 0, 3, '2026-06-27 11:34:35', '2026-06-27 19:06:32'),
(363, 14, 'controle', 'observe', 'Ajouter observation', 0, 4, '2026-06-27 11:34:35', '2026-06-27 19:06:32'),
(364, 14, 'ordonnancement', 'fractionner_np', 'Fractionner NP', 0, 3, '2026-06-27 11:34:35', '2026-06-27 19:06:33'),
(365, 14, 'paiements', 'view', 'Voir paiements', 0, 1, '2026-06-27 11:34:36', '2026-06-27 19:06:33'),
(366, 14, 'paiements', 'add_np', 'Payer NP', 0, 2, '2026-06-27 11:34:36', '2026-06-27 19:06:33'),
(367, 14, 'paiements', 'add_npf', 'Payer NPF', 0, 3, '2026-06-27 11:34:36', '2026-06-27 19:06:33'),
(368, 14, 'paiements', 'edit', 'Modifier paiement', 0, 4, '2026-06-27 11:34:36', '2026-06-27 19:06:33'),
(369, 14, 'paiements', 'print', 'Imprimer reçu', 0, 5, '2026-06-27 11:34:36', '2026-06-27 19:06:33'),
(370, 14, 'recouvrement', 'amr', 'Générer AMR', 0, 4, '2026-06-27 11:34:36', '2026-06-27 19:06:34'),
(371, 14, 'recouvrement', 'print', 'Imprimer documents', 0, 5, '2026-06-27 11:34:36', '2026-06-27 19:06:34'),
(372, 14, 'apurement', 'view', 'Voir apurements', 1, 1, '2026-06-27 11:34:37', '2026-06-27 19:06:34'),
(373, 14, 'apurement', 'create', 'Créer apurement', 1, 2, '2026-06-27 11:34:37', '2026-06-27 19:06:34'),
(374, 14, 'apurement', 'validate', 'Valider apurement', 1, 3, '2026-06-27 11:34:37', '2026-06-27 19:06:34'),
(375, 14, 'apurement', 'print', 'Imprimer apurement', 0, 4, '2026-06-27 11:34:37', '2026-06-27 19:06:34'),
(376, 14, 'amr', 'create', 'Créer AMR', 0, 2, '2026-06-27 11:34:37', '2026-06-27 19:06:34'),
(377, 14, 'quittances', 'view', 'Voir quittances', 0, 1, '2026-06-27 11:34:37', '2026-06-27 19:06:34'),
(378, 14, 'quittances', 'create', 'Créer quittance', 0, 2, '2026-06-27 11:34:37', '2026-06-27 19:06:34'),
(379, 14, 'quittances', 'print', 'Imprimer quittance', 0, 3, '2026-06-27 11:34:38', '2026-06-27 19:06:35'),
(380, 14, 'penalites', 'manage', 'Gérer barème pénalités', 0, 2, '2026-06-27 11:34:38', '2026-06-27 19:06:35'),
(381, 14, 'inspection', 'scan', 'Scanner QR', 0, 2, '2026-06-27 11:34:38', '2026-06-27 19:06:35'),
(382, 14, 'inspection', 'verify', 'Vérifier document', 0, 3, '2026-06-27 11:34:38', '2026-06-27 19:06:35'),
(383, 14, 'inspection', 'revoke', 'Révoquer document', 0, 4, '2026-06-27 11:34:38', '2026-06-27 19:06:35'),
(384, 14, 'inspection', 'fraud', 'Voir fraudes suspectes', 0, 5, '2026-06-27 11:34:38', '2026-06-27 19:06:35'),
(385, 14, 'inspection', 'alerts', 'Voir alertes', 0, 6, '2026-06-27 11:34:38', '2026-06-27 19:06:35'),
(386, 14, 'corrections', 'create', 'Créer correction', 0, 2, '2026-06-27 11:34:39', '2026-06-27 19:06:36'),
(387, 14, 'corrections', 'validate', 'Valider correction', 0, 3, '2026-06-27 11:34:39', '2026-06-27 19:06:36'),
(388, 14, 'parametrage', 'view', 'Voir paramètres', 0, 1, '2026-06-27 11:34:39', '2026-06-27 19:06:36'),
(389, 14, 'parametrage', 'manage', 'Gérer paramètres', 0, 2, '2026-06-27 11:34:39', '2026-06-27 19:06:36'),
(390, 14, 'parametrage', 'nomenclature', 'Gérer nomenclature', 0, 3, '2026-06-27 11:34:39', '2026-06-27 19:06:36'),
(391, 14, 'parametrage', 'directions', 'Gérer directions', 0, 4, '2026-06-27 11:34:39', '2026-06-27 19:06:36'),
(392, 14, 'parametrage', 'services', 'Gérer services', 0, 5, '2026-06-27 11:34:39', '2026-06-27 19:06:36'),
(393, 14, 'parametrage', 'periodes', 'Gérer périodes', 0, 6, '2026-06-27 11:34:39', '2026-06-27 19:06:36'),
(394, 14, 'parametrage', 'taux_change', 'Gérer taux change', 0, 7, '2026-06-27 11:34:39', '2026-06-27 19:06:36'),
(395, 14, 'users', 'status', 'Activer / désactiver utilisateur', 0, 5, '2026-06-27 11:34:40', '2026-06-27 19:06:37'),
(396, 14, 'users', 'password', 'Changer mot de passe', 0, 6, '2026-06-27 11:34:40', '2026-06-27 19:06:37'),
(397, 14, 'administration', 'backup', 'Sauvegardes', 0, 3, '2026-06-27 11:34:40', '2026-06-27 19:06:38'),
(398, 14, 'administration', 'settings', 'Paramètres système', 0, 4, '2026-06-27 11:34:40', '2026-06-27 19:06:38'),
(399, 14, 'pwa', 'reports', 'Rapports PWA', 0, 5, '2026-06-27 11:34:41', '2026-06-27 19:06:38'),
(412, 5, 'liquidation', 'view', 'Voir liquidation', 0, 1, '2026-06-27 13:05:09', '2026-06-27 16:21:12'),
(413, 5, 'liquidation', 'create_nd', 'Créer ND', 0, 2, '2026-06-27 13:05:09', '2026-06-27 16:21:13'),
(414, 5, 'liquidation', 'validate_nd', 'Valider ND', 0, 3, '2026-06-27 13:05:09', '2026-06-27 16:21:13'),
(415, 5, 'liquidation', 'reject_nd', 'Rejeter ND', 0, 4, '2026-06-27 13:05:09', '2026-06-27 16:21:13'),
(416, 5, 'liquidation', 'print_nd', 'Imprimer ND', 0, 5, '2026-06-27 13:05:09', '2026-06-27 16:21:13'),
(417, 5, 'controle', 'view', 'Voir contrôle', 0, 1, '2026-06-27 13:05:09', '2026-06-27 16:21:13'),
(418, 5, 'controle', 'validate', 'Valider contrôle', 0, 2, '2026-06-27 13:05:09', '2026-06-27 16:21:13'),
(419, 5, 'controle', 'reject', 'Rejeter contrôle', 0, 3, '2026-06-27 13:05:09', '2026-06-27 16:21:13'),
(420, 5, 'controle', 'observe', 'Ajouter observation', 0, 4, '2026-06-27 13:05:09', '2026-06-27 16:21:14'),
(421, 5, 'ordonnancement', 'view', 'Voir ordonnancement', 0, 1, '2026-06-27 13:05:10', '2026-06-27 16:21:14'),
(422, 5, 'ordonnancement', 'create_np', 'Créer NP', 0, 2, '2026-06-27 13:05:10', '2026-06-27 16:21:14'),
(423, 5, 'ordonnancement', 'fractionner_np', 'Fractionner NP', 0, 3, '2026-06-27 13:05:10', '2026-06-27 16:21:14'),
(424, 5, 'ordonnancement', 'avis_fractionnement', 'Créer avis de fractionnement', 0, 4, '2026-06-27 13:05:10', '2026-06-27 16:21:14'),
(425, 5, 'ordonnancement', 'print_np', 'Imprimer NP', 0, 5, '2026-06-27 13:05:10', '2026-06-27 16:21:14'),
(426, 5, 'paiements', 'view', 'Voir paiements', 0, 1, '2026-06-27 13:05:10', '2026-06-27 16:21:14'),
(427, 5, 'paiements', 'add_np', 'Payer NP', 0, 2, '2026-06-27 13:05:10', '2026-06-27 16:21:14'),
(428, 5, 'paiements', 'add_npf', 'Payer NPF', 0, 3, '2026-06-27 13:05:10', '2026-06-27 16:21:14'),
(429, 5, 'paiements', 'edit', 'Modifier paiement', 0, 4, '2026-06-27 13:05:10', '2026-06-27 16:21:14'),
(430, 5, 'paiements', 'print', 'Imprimer reçu', 0, 5, '2026-06-27 13:05:10', '2026-06-27 16:21:14'),
(431, 5, 'recouvrement', 'view', 'Voir recouvrement', 0, 1, '2026-06-27 13:05:10', '2026-06-27 16:21:14'),
(432, 5, 'recouvrement', 'apurement', 'Apurer NP / NPF', 0, 2, '2026-06-27 13:05:10', '2026-06-27 16:21:15'),
(433, 5, 'recouvrement', 'quittance', 'Générer quittance', 0, 3, '2026-06-27 13:05:10', '2026-06-27 16:21:15'),
(434, 5, 'recouvrement', 'amr', 'Générer AMR', 0, 4, '2026-06-27 13:05:10', '2026-06-27 16:21:15'),
(435, 5, 'recouvrement', 'print', 'Imprimer documents', 0, 5, '2026-06-27 13:05:11', '2026-06-27 16:21:15'),
(436, 5, 'apurement', 'view', 'Voir apurements', 0, 1, '2026-06-27 13:05:11', '2026-06-27 16:21:15'),
(437, 5, 'apurement', 'create', 'Créer apurement', 0, 2, '2026-06-27 13:05:11', '2026-06-27 16:21:15'),
(438, 5, 'apurement', 'validate', 'Valider apurement', 0, 3, '2026-06-27 13:05:11', '2026-06-27 16:21:15'),
(439, 5, 'apurement', 'print', 'Imprimer apurement', 0, 4, '2026-06-27 13:05:11', '2026-06-27 16:21:15'),
(440, 5, 'amr', 'view', 'Voir AMR', 0, 1, '2026-06-27 13:05:11', '2026-06-27 16:21:15'),
(441, 5, 'amr', 'create', 'Créer AMR', 0, 2, '2026-06-27 13:05:11', '2026-06-27 16:21:15'),
(442, 5, 'amr', 'print', 'Imprimer AMR', 0, 3, '2026-06-27 13:05:11', '2026-06-27 16:21:15'),
(443, 5, 'quittances', 'view', 'Voir quittances', 0, 1, '2026-06-27 13:05:11', '2026-06-27 16:21:15'),
(444, 5, 'quittances', 'create', 'Créer quittance', 0, 2, '2026-06-27 13:05:11', '2026-06-27 16:21:15'),
(445, 5, 'quittances', 'print', 'Imprimer quittance', 0, 3, '2026-06-27 13:05:11', '2026-06-27 16:21:16'),
(446, 5, 'penalites', 'view', 'Voir pénalités', 0, 1, '2026-06-27 13:05:11', '2026-06-27 16:21:16'),
(447, 5, 'penalites', 'manage', 'Gérer barème pénalités', 0, 2, '2026-06-27 13:05:11', '2026-06-27 16:21:16'),
(448, 5, 'penalites', 'history', 'Voir historique pénalités', 0, 3, '2026-06-27 13:05:12', '2026-06-27 16:21:16'),
(449, 5, 'inspection', 'view', 'Voir inspection', 0, 1, '2026-06-27 13:05:12', '2026-06-27 16:21:16'),
(450, 5, 'inspection', 'scan', 'Scanner QR', 0, 2, '2026-06-27 13:05:12', '2026-06-27 16:21:16'),
(451, 5, 'inspection', 'verify', 'Vérifier document', 0, 3, '2026-06-27 13:05:12', '2026-06-27 16:21:16'),
(452, 5, 'inspection', 'revoke', 'Révoquer document', 0, 4, '2026-06-27 13:05:12', '2026-06-27 16:21:16'),
(453, 5, 'inspection', 'fraud', 'Voir fraudes suspectes', 0, 5, '2026-06-27 13:05:12', '2026-06-27 16:21:16'),
(454, 5, 'inspection', 'alerts', 'Voir alertes', 0, 6, '2026-06-27 13:05:12', '2026-06-27 16:21:16'),
(455, 5, 'corrections', 'view', 'Voir corrections', 0, 1, '2026-06-27 13:05:12', '2026-06-27 16:21:16'),
(456, 5, 'corrections', 'create', 'Créer correction', 0, 2, '2026-06-27 13:05:12', '2026-06-27 16:21:16'),
(457, 5, 'corrections', 'validate', 'Valider correction', 0, 3, '2026-06-27 13:05:12', '2026-06-27 16:21:17'),
(458, 5, 'corrections', 'history', 'Historique corrections', 0, 4, '2026-06-27 13:05:12', '2026-06-27 16:21:17'),
(459, 5, 'parametrage', 'view', 'Voir paramètres', 0, 1, '2026-06-27 13:05:12', '2026-06-27 16:21:17'),
(460, 5, 'parametrage', 'manage', 'Gérer paramètres', 0, 2, '2026-06-27 13:05:13', '2026-06-27 16:21:17'),
(461, 5, 'parametrage', 'nomenclature', 'Gérer nomenclature', 0, 3, '2026-06-27 13:05:13', '2026-06-27 16:21:17'),
(462, 5, 'parametrage', 'directions', 'Gérer directions', 0, 4, '2026-06-27 13:05:13', '2026-06-27 16:21:17'),
(463, 5, 'parametrage', 'services', 'Gérer services', 0, 5, '2026-06-27 13:05:13', '2026-06-27 16:21:17'),
(464, 5, 'parametrage', 'periodes', 'Gérer périodes', 0, 6, '2026-06-27 13:05:13', '2026-06-27 16:21:17'),
(465, 5, 'parametrage', 'taux_change', 'Gérer taux change', 0, 7, '2026-06-27 13:05:13', '2026-06-27 16:21:17'),
(466, 5, 'users', 'view', 'Voir utilisateurs', 0, 1, '2026-06-27 13:05:13', '2026-06-27 16:21:18'),
(467, 5, 'users', 'add', 'Ajouter utilisateur', 0, 2, '2026-06-27 13:05:13', '2026-06-27 16:21:18'),
(468, 5, 'users', 'edit', 'Modifier utilisateur', 0, 3, '2026-06-27 13:05:13', '2026-06-27 16:21:18'),
(469, 5, 'users', 'delete', 'Supprimer utilisateur', 0, 4, '2026-06-27 13:05:13', '2026-06-27 16:21:18'),
(470, 5, 'users', 'status', 'Activer / désactiver utilisateur', 0, 5, '2026-06-27 13:05:13', '2026-06-27 16:21:18'),
(471, 5, 'users', 'password', 'Changer mot de passe', 0, 6, '2026-06-27 13:05:13', '2026-06-27 16:21:18'),
(472, 5, 'roles', 'view', 'Voir rôles', 0, 1, '2026-06-27 13:05:13', '2026-06-27 16:21:18'),
(473, 5, 'roles', 'add', 'Ajouter rôle', 0, 2, '2026-06-27 13:05:13', '2026-06-27 16:21:18'),
(474, 5, 'roles', 'edit', 'Modifier rôle', 0, 3, '2026-06-27 13:05:14', '2026-06-27 16:21:18'),
(475, 5, 'roles', 'delete', 'Supprimer rôle', 0, 4, '2026-06-27 13:05:14', '2026-06-27 16:21:18'),
(476, 5, 'roles', 'permissions', 'Gérer permissions', 0, 5, '2026-06-27 13:05:14', '2026-06-27 16:21:18'),
(477, 5, 'administration', 'view', 'Voir administration', 0, 1, '2026-06-27 13:05:14', '2026-06-27 16:21:19'),
(478, 5, 'administration', 'logs', 'Voir journaux', 0, 2, '2026-06-27 13:05:14', '2026-06-27 16:21:19'),
(479, 5, 'administration', 'backup', 'Sauvegardes', 0, 3, '2026-06-27 13:05:14', '2026-06-27 16:21:19'),
(480, 5, 'administration', 'settings', 'Paramètres système', 0, 4, '2026-06-27 13:05:14', '2026-06-27 16:21:19'),
(481, 5, 'pwa', 'view', 'Voir PWA', 1, 1, '2026-06-27 13:05:14', '2026-06-27 16:21:19'),
(482, 5, 'pwa', 'sync', 'Synchronisation PWA', 1, 2, '2026-06-27 13:05:14', '2026-06-27 16:21:19'),
(483, 5, 'pwa', 'backup', 'Sauvegarde PWA', 0, 3, '2026-06-27 13:05:14', '2026-06-27 16:21:19'),
(484, 5, 'pwa', 'agents', 'Agents terrain', 0, 4, '2026-06-27 13:05:14', '2026-06-27 16:21:19'),
(485, 5, 'pwa', 'reports', 'Rapports PWA', 0, 5, '2026-06-27 13:05:14', '2026-06-27 16:21:19'),
(486, 5, 'dashboard', 'view', 'Voir le tableau de bord', 1, 1, '2026-06-27 13:38:10', '2026-06-27 16:21:11'),
(487, 5, 'contribuables', 'view', 'Voir contribuables', 1, 1, '2026-06-27 13:38:10', '2026-06-27 16:21:11'),
(488, 5, 'contribuables', 'add', 'Ajouter contribuable', 1, 2, '2026-06-27 13:38:10', '2026-06-27 16:21:12'),
(489, 5, 'contribuables', 'import', 'Importer contribuables', 1, 5, '2026-06-27 13:38:10', '2026-06-27 16:21:12'),
(490, 5, 'constatation', 'view', 'Voir NT', 1, 1, '2026-06-27 13:38:10', '2026-06-27 16:21:12'),
(491, 5, 'constatation', 'add', 'Créer NT', 1, 2, '2026-06-27 13:38:10', '2026-06-27 16:21:12'),
(492, 5, 'constatation', 'submit', 'Soumettre NT', 1, 5, '2026-06-27 13:38:10', '2026-06-27 16:21:12'),
(493, 5, 'constatation', 'print', 'Imprimer NT', 1, 6, '2026-06-27 13:38:10', '2026-06-27 16:21:12'),
(494, 6, 'dashboard', 'view', 'Voir le tableau de bord', 0, 1, '2026-06-27 14:20:35', '2026-06-27 16:28:12'),
(495, 6, 'contribuables', 'view', 'Voir contribuables', 0, 1, '2026-06-27 14:20:35', '2026-06-27 16:28:12'),
(496, 6, 'contribuables', 'add', 'Ajouter contribuable', 0, 2, '2026-06-27 14:20:35', '2026-06-27 16:28:13'),
(497, 6, 'contribuables', 'edit', 'Modifier contribuable', 0, 3, '2026-06-27 14:20:35', '2026-06-27 16:28:13'),
(498, 6, 'contribuables', 'delete', 'Supprimer contribuable', 0, 4, '2026-06-27 14:20:36', '2026-06-27 16:28:13'),
(499, 6, 'contribuables', 'import', 'Importer contribuables', 0, 5, '2026-06-27 14:20:36', '2026-06-27 16:28:13'),
(500, 6, 'constatation', 'view', 'Voir NT', 0, 1, '2026-06-27 14:20:36', '2026-06-27 16:28:13'),
(501, 6, 'constatation', 'add', 'Créer NT', 0, 2, '2026-06-27 14:20:36', '2026-06-27 16:28:13'),
(502, 6, 'constatation', 'edit', 'Modifier NT', 0, 3, '2026-06-27 14:20:36', '2026-06-27 16:28:13'),
(503, 6, 'constatation', 'delete', 'Supprimer NT', 0, 4, '2026-06-27 14:20:36', '2026-06-27 16:28:13'),
(504, 6, 'constatation', 'submit', 'Soumettre NT', 0, 5, '2026-06-27 14:20:36', '2026-06-27 16:28:13'),
(505, 6, 'constatation', 'print', 'Imprimer NT', 0, 6, '2026-06-27 14:20:36', '2026-06-27 16:28:14'),
(506, 6, 'liquidation', 'view', 'Voir liquidation', 1, 1, '2026-06-27 14:20:36', '2026-06-27 16:28:14'),
(507, 6, 'liquidation', 'create_nd', 'Créer ND', 1, 2, '2026-06-27 14:20:36', '2026-06-27 16:28:14'),
(508, 6, 'liquidation', 'validate_nd', 'Valider ND', 1, 3, '2026-06-27 14:20:37', '2026-06-27 16:28:14'),
(509, 6, 'liquidation', 'reject_nd', 'Rejeter ND', 1, 4, '2026-06-27 14:20:37', '2026-06-27 16:28:14'),
(510, 6, 'liquidation', 'print_nd', 'Imprimer ND', 1, 5, '2026-06-27 14:20:37', '2026-06-27 16:28:14'),
(511, 6, 'controle', 'view', 'Voir contrôle', 0, 1, '2026-06-27 14:20:37', '2026-06-27 16:28:14'),
(512, 6, 'controle', 'validate', 'Valider contrôle', 0, 2, '2026-06-27 14:20:37', '2026-06-27 16:28:14'),
(513, 6, 'controle', 'reject', 'Rejeter contrôle', 0, 3, '2026-06-27 14:20:37', '2026-06-27 16:28:14'),
(514, 6, 'controle', 'observe', 'Ajouter observation', 0, 4, '2026-06-27 14:20:37', '2026-06-27 16:28:14'),
(515, 6, 'ordonnancement', 'view', 'Voir ordonnancement', 0, 1, '2026-06-27 14:20:37', '2026-06-27 16:28:14'),
(516, 6, 'ordonnancement', 'create_np', 'Créer NP', 0, 2, '2026-06-27 14:20:38', '2026-06-27 16:28:14'),
(517, 6, 'ordonnancement', 'fractionner_np', 'Fractionner NP', 0, 3, '2026-06-27 14:20:38', '2026-06-27 16:28:15'),
(518, 6, 'ordonnancement', 'avis_fractionnement', 'Créer avis de fractionnement', 0, 4, '2026-06-27 14:20:38', '2026-06-27 16:28:15'),
(519, 6, 'ordonnancement', 'print_np', 'Imprimer NP', 0, 5, '2026-06-27 14:20:38', '2026-06-27 16:28:15'),
(520, 6, 'paiements', 'view', 'Voir paiements', 0, 1, '2026-06-27 14:20:38', '2026-06-27 16:28:15'),
(521, 6, 'paiements', 'add_np', 'Payer NP', 0, 2, '2026-06-27 14:20:38', '2026-06-27 16:28:15'),
(522, 6, 'paiements', 'add_npf', 'Payer NPF', 0, 3, '2026-06-27 14:20:38', '2026-06-27 16:28:15'),
(523, 6, 'paiements', 'edit', 'Modifier paiement', 0, 4, '2026-06-27 14:20:39', '2026-06-27 16:28:15'),
(524, 6, 'paiements', 'print', 'Imprimer reçu', 0, 5, '2026-06-27 14:20:39', '2026-06-27 16:28:15'),
(525, 6, 'recouvrement', 'view', 'Voir recouvrement', 0, 1, '2026-06-27 14:20:39', '2026-06-27 16:28:15'),
(526, 6, 'recouvrement', 'apurement', 'Apurer NP / NPF', 0, 2, '2026-06-27 14:20:39', '2026-06-27 16:28:15'),
(527, 6, 'recouvrement', 'quittance', 'Générer quittance', 0, 3, '2026-06-27 14:20:39', '2026-06-27 16:28:15'),
(528, 6, 'recouvrement', 'amr', 'Générer AMR', 0, 4, '2026-06-27 14:20:39', '2026-06-27 16:28:15'),
(529, 6, 'recouvrement', 'print', 'Imprimer documents', 0, 5, '2026-06-27 14:20:39', '2026-06-27 16:28:15'),
(530, 6, 'apurement', 'view', 'Voir apurements', 0, 1, '2026-06-27 14:20:39', '2026-06-27 16:28:16'),
(531, 6, 'apurement', 'create', 'Créer apurement', 0, 2, '2026-06-27 14:20:39', '2026-06-27 16:28:16'),
(532, 6, 'apurement', 'validate', 'Valider apurement', 0, 3, '2026-06-27 14:20:39', '2026-06-27 16:28:16'),
(533, 6, 'apurement', 'print', 'Imprimer apurement', 0, 4, '2026-06-27 14:20:39', '2026-06-27 16:28:16');
INSERT INTO `permissions` (`id`, `role_id`, `module`, `action`, `description`, `autorise`, `ordre`, `created_at`, `updated_at`) VALUES
(534, 6, 'amr', 'view', 'Voir AMR', 0, 1, '2026-06-27 14:20:39', '2026-06-27 16:28:16'),
(535, 6, 'amr', 'create', 'Créer AMR', 0, 2, '2026-06-27 14:20:40', '2026-06-27 16:28:16'),
(536, 6, 'amr', 'print', 'Imprimer AMR', 0, 3, '2026-06-27 14:20:40', '2026-06-27 16:28:16'),
(537, 6, 'quittances', 'view', 'Voir quittances', 0, 1, '2026-06-27 14:20:40', '2026-06-27 16:28:16'),
(538, 6, 'quittances', 'create', 'Créer quittance', 0, 2, '2026-06-27 14:20:40', '2026-06-27 16:28:16'),
(539, 6, 'quittances', 'print', 'Imprimer quittance', 0, 3, '2026-06-27 14:20:40', '2026-06-27 16:28:16'),
(540, 6, 'penalites', 'view', 'Voir pénalités', 0, 1, '2026-06-27 14:20:40', '2026-06-27 16:28:17'),
(541, 6, 'penalites', 'manage', 'Gérer barème pénalités', 0, 2, '2026-06-27 14:20:40', '2026-06-27 16:28:17'),
(542, 6, 'penalites', 'history', 'Voir historique pénalités', 0, 3, '2026-06-27 14:20:40', '2026-06-27 16:28:17'),
(543, 6, 'inspection', 'view', 'Voir inspection', 0, 1, '2026-06-27 14:20:40', '2026-06-27 16:28:17'),
(544, 6, 'inspection', 'scan', 'Scanner QR', 0, 2, '2026-06-27 14:20:40', '2026-06-27 16:28:17'),
(545, 6, 'inspection', 'verify', 'Vérifier document', 0, 3, '2026-06-27 14:20:40', '2026-06-27 16:28:17'),
(546, 6, 'inspection', 'revoke', 'Révoquer document', 0, 4, '2026-06-27 14:20:40', '2026-06-27 16:28:17'),
(547, 6, 'inspection', 'fraud', 'Voir fraudes suspectes', 0, 5, '2026-06-27 14:20:41', '2026-06-27 16:28:17'),
(548, 6, 'inspection', 'alerts', 'Voir alertes', 0, 6, '2026-06-27 14:20:41', '2026-06-27 16:28:17'),
(549, 6, 'corrections', 'view', 'Voir corrections', 0, 1, '2026-06-27 14:20:41', '2026-06-27 16:28:17'),
(550, 6, 'corrections', 'create', 'Créer correction', 0, 2, '2026-06-27 14:20:41', '2026-06-27 16:28:17'),
(551, 6, 'corrections', 'validate', 'Valider correction', 0, 3, '2026-06-27 14:20:41', '2026-06-27 16:28:17'),
(552, 6, 'corrections', 'history', 'Historique corrections', 0, 4, '2026-06-27 14:20:41', '2026-06-27 16:28:17'),
(553, 6, 'parametrage', 'view', 'Voir paramètres', 0, 1, '2026-06-27 14:20:41', '2026-06-27 16:28:18'),
(554, 6, 'parametrage', 'manage', 'Gérer paramètres', 0, 2, '2026-06-27 14:20:42', '2026-06-27 16:28:18'),
(555, 6, 'parametrage', 'nomenclature', 'Gérer nomenclature', 0, 3, '2026-06-27 14:20:42', '2026-06-27 16:28:18'),
(556, 6, 'parametrage', 'directions', 'Gérer directions', 0, 4, '2026-06-27 14:20:42', '2026-06-27 16:28:18'),
(557, 6, 'parametrage', 'services', 'Gérer services', 0, 5, '2026-06-27 14:20:42', '2026-06-27 16:28:18'),
(558, 6, 'parametrage', 'periodes', 'Gérer périodes', 0, 6, '2026-06-27 14:20:42', '2026-06-27 16:28:18'),
(559, 6, 'parametrage', 'taux_change', 'Gérer taux change', 0, 7, '2026-06-27 14:20:42', '2026-06-27 16:28:18'),
(560, 6, 'users', 'view', 'Voir utilisateurs', 0, 1, '2026-06-27 14:20:42', '2026-06-27 16:28:18'),
(561, 6, 'users', 'add', 'Ajouter utilisateur', 0, 2, '2026-06-27 14:20:43', '2026-06-27 16:28:18'),
(562, 6, 'users', 'edit', 'Modifier utilisateur', 0, 3, '2026-06-27 14:20:43', '2026-06-27 16:28:19'),
(563, 6, 'users', 'delete', 'Supprimer utilisateur', 0, 4, '2026-06-27 14:20:43', '2026-06-27 16:28:19'),
(564, 6, 'users', 'status', 'Activer / désactiver utilisateur', 0, 5, '2026-06-27 14:20:43', '2026-06-27 16:28:19'),
(565, 6, 'users', 'password', 'Changer mot de passe', 0, 6, '2026-06-27 14:20:43', '2026-06-27 16:28:19'),
(566, 6, 'roles', 'view', 'Voir rôles', 0, 1, '2026-06-27 14:20:43', '2026-06-27 16:28:19'),
(567, 6, 'roles', 'add', 'Ajouter rôle', 0, 2, '2026-06-27 14:20:43', '2026-06-27 16:28:19'),
(568, 6, 'roles', 'edit', 'Modifier rôle', 0, 3, '2026-06-27 14:20:43', '2026-06-27 16:28:19'),
(569, 6, 'roles', 'delete', 'Supprimer rôle', 0, 4, '2026-06-27 14:20:44', '2026-06-27 16:28:19'),
(570, 6, 'roles', 'permissions', 'Gérer permissions', 0, 5, '2026-06-27 14:20:44', '2026-06-27 16:28:19'),
(571, 6, 'administration', 'view', 'Voir administration', 0, 1, '2026-06-27 14:20:44', '2026-06-27 16:28:19'),
(572, 6, 'administration', 'logs', 'Voir journaux', 0, 2, '2026-06-27 14:20:44', '2026-06-27 16:28:19'),
(573, 6, 'administration', 'backup', 'Sauvegardes', 0, 3, '2026-06-27 14:20:44', '2026-06-27 16:28:19'),
(574, 6, 'administration', 'settings', 'Paramètres système', 0, 4, '2026-06-27 14:20:44', '2026-06-27 16:28:19'),
(575, 6, 'pwa', 'view', 'Voir PWA', 0, 1, '2026-06-27 14:20:44', '2026-06-27 16:28:20'),
(576, 6, 'pwa', 'sync', 'Synchronisation PWA', 0, 2, '2026-06-27 14:20:44', '2026-06-27 16:28:20'),
(577, 6, 'pwa', 'backup', 'Sauvegarde PWA', 0, 3, '2026-06-27 14:20:44', '2026-06-27 16:28:20'),
(578, 6, 'pwa', 'agents', 'Agents terrain', 0, 4, '2026-06-27 14:20:44', '2026-06-27 16:28:20'),
(579, 6, 'pwa', 'reports', 'Rapports PWA', 0, 5, '2026-06-27 14:20:44', '2026-06-27 16:28:20'),
(580, 5, 'contribuables', 'edit', 'Modifier contribuable', 0, 3, '2026-06-27 14:21:12', NULL),
(581, 5, 'contribuables', 'delete', 'Supprimer contribuable', 0, 4, '2026-06-27 14:21:12', NULL),
(582, 5, 'constatation', 'edit', 'Modifier NT', 0, 3, '2026-06-27 14:21:12', NULL),
(583, 5, 'constatation', 'delete', 'Supprimer NT', 0, 4, '2026-06-27 14:21:12', NULL),
(585, 7, 'contribuables', 'view', 'Voir contribuables', 0, 1, '2026-06-27 14:31:05', NULL),
(586, 7, 'contribuables', 'add', 'Ajouter contribuable', 0, 2, '2026-06-27 14:31:05', NULL),
(587, 7, 'contribuables', 'edit', 'Modifier contribuable', 0, 3, '2026-06-27 14:31:05', NULL),
(588, 7, 'contribuables', 'delete', 'Supprimer contribuable', 0, 4, '2026-06-27 14:31:05', NULL),
(589, 7, 'contribuables', 'import', 'Importer contribuables', 0, 5, '2026-06-27 14:31:05', NULL),
(590, 7, 'constatation', 'view', 'Voir NT', 0, 1, '2026-06-27 14:31:06', NULL),
(591, 7, 'constatation', 'add', 'Créer NT', 0, 2, '2026-06-27 14:31:06', NULL),
(592, 7, 'constatation', 'edit', 'Modifier NT', 0, 3, '2026-06-27 14:31:06', NULL),
(593, 7, 'constatation', 'delete', 'Supprimer NT', 0, 4, '2026-06-27 14:31:06', NULL),
(594, 7, 'constatation', 'submit', 'Soumettre NT', 0, 5, '2026-06-27 14:31:06', NULL),
(595, 7, 'constatation', 'print', 'Imprimer NT', 0, 6, '2026-06-27 14:31:06', NULL),
(605, 7, 'ordonnancement', 'view', 'Voir ordonnancement', 0, 1, '2026-06-27 14:31:08', NULL),
(606, 7, 'ordonnancement', 'create_np', 'Créer NP', 0, 2, '2026-06-27 14:31:08', NULL),
(607, 7, 'ordonnancement', 'fractionner_np', 'Fractionner NP', 0, 3, '2026-06-27 14:31:08', NULL),
(608, 7, 'ordonnancement', 'avis_fractionnement', 'Créer avis de fractionnement', 0, 4, '2026-06-27 14:31:08', NULL),
(609, 7, 'ordonnancement', 'print_np', 'Imprimer NP', 0, 5, '2026-06-27 14:31:08', NULL),
(610, 7, 'paiements', 'view', 'Voir paiements', 0, 1, '2026-06-27 14:31:08', NULL),
(611, 7, 'paiements', 'add_np', 'Payer NP', 0, 2, '2026-06-27 14:31:08', NULL),
(612, 7, 'paiements', 'add_npf', 'Payer NPF', 0, 3, '2026-06-27 14:31:08', NULL),
(613, 7, 'paiements', 'edit', 'Modifier paiement', 0, 4, '2026-06-27 14:31:08', NULL),
(614, 7, 'paiements', 'print', 'Imprimer reçu', 0, 5, '2026-06-27 14:31:08', NULL),
(615, 7, 'recouvrement', 'view', 'Voir recouvrement', 0, 1, '2026-06-27 14:31:08', NULL),
(616, 7, 'recouvrement', 'apurement', 'Apurer NP / NPF', 0, 2, '2026-06-27 14:31:09', NULL),
(617, 7, 'recouvrement', 'quittance', 'Générer quittance', 0, 3, '2026-06-27 14:31:09', NULL),
(618, 7, 'recouvrement', 'amr', 'Générer AMR', 0, 4, '2026-06-27 14:31:09', NULL),
(619, 7, 'recouvrement', 'print', 'Imprimer documents', 0, 5, '2026-06-27 14:31:09', NULL),
(620, 7, 'apurement', 'view', 'Voir apurements', 0, 1, '2026-06-27 14:31:09', NULL),
(621, 7, 'apurement', 'create', 'Créer apurement', 0, 2, '2026-06-27 14:31:09', NULL),
(622, 7, 'apurement', 'validate', 'Valider apurement', 0, 3, '2026-06-27 14:31:09', NULL),
(623, 7, 'apurement', 'print', 'Imprimer apurement', 0, 4, '2026-06-27 14:31:09', NULL),
(624, 7, 'amr', 'view', 'Voir AMR', 0, 1, '2026-06-27 14:31:09', NULL),
(625, 7, 'amr', 'create', 'Créer AMR', 0, 2, '2026-06-27 14:31:09', NULL),
(626, 7, 'amr', 'print', 'Imprimer AMR', 0, 3, '2026-06-27 14:31:09', NULL),
(627, 7, 'quittances', 'view', 'Voir quittances', 0, 1, '2026-06-27 14:31:09', NULL),
(628, 7, 'quittances', 'create', 'Créer quittance', 0, 2, '2026-06-27 14:31:10', NULL),
(629, 7, 'quittances', 'print', 'Imprimer quittance', 0, 3, '2026-06-27 14:31:10', NULL),
(630, 7, 'penalites', 'view', 'Voir pénalités', 0, 1, '2026-06-27 14:31:10', NULL),
(631, 7, 'penalites', 'manage', 'Gérer barème pénalités', 0, 2, '2026-06-27 14:31:10', NULL),
(632, 7, 'penalites', 'history', 'Voir historique pénalités', 0, 3, '2026-06-27 14:31:10', NULL),
(633, 7, 'inspection', 'view', 'Voir inspection', 0, 1, '2026-06-27 14:31:10', NULL),
(634, 7, 'inspection', 'scan', 'Scanner QR', 0, 2, '2026-06-27 14:31:10', NULL),
(635, 7, 'inspection', 'verify', 'Vérifier document', 0, 3, '2026-06-27 14:31:10', NULL),
(636, 7, 'inspection', 'revoke', 'Révoquer document', 0, 4, '2026-06-27 14:31:10', NULL),
(637, 7, 'inspection', 'fraud', 'Voir fraudes suspectes', 0, 5, '2026-06-27 14:31:10', NULL),
(638, 7, 'inspection', 'alerts', 'Voir alertes', 0, 6, '2026-06-27 14:31:10', NULL),
(639, 7, 'corrections', 'view', 'Voir corrections', 0, 1, '2026-06-27 14:31:10', NULL),
(640, 7, 'corrections', 'create', 'Créer correction', 0, 2, '2026-06-27 14:31:11', NULL),
(641, 7, 'corrections', 'validate', 'Valider correction', 0, 3, '2026-06-27 14:31:11', NULL),
(642, 7, 'corrections', 'history', 'Historique corrections', 0, 4, '2026-06-27 14:31:11', NULL),
(643, 7, 'parametrage', 'view', 'Voir paramètres', 0, 1, '2026-06-27 14:31:11', NULL),
(644, 7, 'parametrage', 'manage', 'Gérer paramètres', 0, 2, '2026-06-27 14:31:11', NULL),
(645, 7, 'parametrage', 'nomenclature', 'Gérer nomenclature', 0, 3, '2026-06-27 14:31:11', NULL),
(646, 7, 'parametrage', 'directions', 'Gérer directions', 0, 4, '2026-06-27 14:31:11', NULL),
(647, 7, 'parametrage', 'services', 'Gérer services', 0, 5, '2026-06-27 14:31:11', NULL),
(648, 7, 'parametrage', 'periodes', 'Gérer périodes', 0, 6, '2026-06-27 14:31:11', NULL),
(649, 7, 'parametrage', 'taux_change', 'Gérer taux change', 0, 7, '2026-06-27 14:31:12', NULL),
(650, 7, 'users', 'view', 'Voir utilisateurs', 0, 1, '2026-06-27 14:31:12', NULL),
(651, 7, 'users', 'add', 'Ajouter utilisateur', 0, 2, '2026-06-27 14:31:12', NULL),
(652, 7, 'users', 'edit', 'Modifier utilisateur', 0, 3, '2026-06-27 14:31:12', NULL),
(653, 7, 'users', 'delete', 'Supprimer utilisateur', 0, 4, '2026-06-27 14:31:12', NULL),
(654, 7, 'users', 'status', 'Activer / désactiver utilisateur', 0, 5, '2026-06-27 14:31:12', NULL),
(655, 7, 'users', 'password', 'Changer mot de passe', 0, 6, '2026-06-27 14:31:12', NULL),
(656, 7, 'roles', 'view', 'Voir rôles', 0, 1, '2026-06-27 14:31:12', NULL),
(657, 7, 'roles', 'add', 'Ajouter rôle', 0, 2, '2026-06-27 14:31:12', NULL),
(658, 7, 'roles', 'edit', 'Modifier rôle', 0, 3, '2026-06-27 14:31:12', NULL),
(659, 7, 'roles', 'delete', 'Supprimer rôle', 0, 4, '2026-06-27 14:31:12', NULL),
(660, 7, 'roles', 'permissions', 'Gérer permissions', 0, 5, '2026-06-27 14:31:13', NULL),
(661, 7, 'administration', 'view', 'Voir administration', 0, 1, '2026-06-27 14:31:13', NULL),
(662, 7, 'administration', 'logs', 'Voir journaux', 0, 2, '2026-06-27 14:31:13', NULL),
(663, 7, 'administration', 'backup', 'Sauvegardes', 0, 3, '2026-06-27 14:31:13', NULL),
(664, 7, 'administration', 'settings', 'Paramètres système', 0, 4, '2026-06-27 14:31:13', NULL),
(665, 7, 'pwa', 'view', 'Voir PWA', 0, 1, '2026-06-27 14:31:13', NULL),
(666, 7, 'pwa', 'sync', 'Synchronisation PWA', 0, 2, '2026-06-27 14:31:13', NULL),
(667, 7, 'pwa', 'backup', 'Sauvegarde PWA', 0, 3, '2026-06-27 14:31:13', NULL),
(668, 7, 'pwa', 'agents', 'Agents terrain', 0, 4, '2026-06-27 14:31:13', NULL),
(669, 7, 'pwa', 'reports', 'Rapports PWA', 0, 5, '2026-06-27 14:31:13', NULL),
(676, 7, 'dashboard', 'view', NULL, 1, 0, '2026-06-27 14:55:04', NULL),
(677, 7, 'controle', 'view', NULL, 1, 0, '2026-06-27 14:55:04', NULL),
(678, 7, 'controle', 'validate', NULL, 1, 0, '2026-06-27 14:55:04', NULL),
(679, 7, 'controle', 'reject', NULL, 1, 0, '2026-06-27 14:55:04', NULL),
(680, 7, 'controle', 'observe', NULL, 1, 0, '2026-06-27 14:55:04', NULL),
(681, 8, 'dashboard', 'view', 'Voir le tableau de bord', 0, 1, '2026-06-27 15:48:41', NULL),
(682, 8, 'contribuables', 'view', 'Voir contribuables', 0, 1, '2026-06-27 15:48:41', NULL),
(683, 8, 'contribuables', 'add', 'Ajouter contribuable', 0, 2, '2026-06-27 15:48:42', NULL),
(684, 8, 'contribuables', 'edit', 'Modifier contribuable', 0, 3, '2026-06-27 15:48:42', NULL),
(685, 8, 'contribuables', 'delete', 'Supprimer contribuable', 0, 4, '2026-06-27 15:48:42', NULL),
(686, 8, 'contribuables', 'import', 'Importer contribuables', 0, 5, '2026-06-27 15:48:42', NULL),
(687, 8, 'constatation', 'view', 'Voir NT', 0, 1, '2026-06-27 15:48:42', NULL),
(688, 8, 'constatation', 'add', 'Créer NT', 0, 2, '2026-06-27 15:48:42', NULL),
(689, 8, 'constatation', 'edit', 'Modifier NT', 0, 3, '2026-06-27 15:48:42', NULL),
(690, 8, 'constatation', 'delete', 'Supprimer NT', 0, 4, '2026-06-27 15:48:42', NULL),
(691, 8, 'constatation', 'submit', 'Soumettre NT', 0, 5, '2026-06-27 15:48:43', NULL),
(692, 8, 'constatation', 'print', 'Imprimer NT', 0, 6, '2026-06-27 15:48:43', NULL),
(693, 8, 'liquidation', 'view', 'Voir liquidation', 0, 1, '2026-06-27 15:48:43', NULL),
(694, 8, 'liquidation', 'create_nd', 'Créer ND', 0, 2, '2026-06-27 15:48:43', NULL),
(695, 8, 'liquidation', 'validate_nd', 'Valider ND', 0, 3, '2026-06-27 15:48:43', NULL),
(696, 8, 'liquidation', 'reject_nd', 'Rejeter ND', 0, 4, '2026-06-27 15:48:43', NULL),
(697, 8, 'liquidation', 'print_nd', 'Imprimer ND', 0, 5, '2026-06-27 15:48:43', NULL),
(698, 8, 'controle', 'view', 'Voir contrôle', 0, 1, '2026-06-27 15:48:43', NULL),
(699, 8, 'controle', 'validate', 'Valider contrôle', 0, 2, '2026-06-27 15:48:43', NULL),
(700, 8, 'controle', 'reject', 'Rejeter contrôle', 0, 3, '2026-06-27 15:48:43', NULL),
(701, 8, 'controle', 'observe', 'Ajouter observation', 0, 4, '2026-06-27 15:48:43', NULL),
(702, 8, 'ordonnancement', 'view', 'Voir ordonnancement', 1, 1, '2026-06-27 15:48:43', NULL),
(703, 8, 'ordonnancement', 'create_np', 'Créer NP', 1, 2, '2026-06-27 15:48:44', NULL),
(704, 8, 'ordonnancement', 'fractionner_np', 'Fractionner NP', 1, 3, '2026-06-27 15:48:44', NULL),
(705, 8, 'ordonnancement', 'avis_fractionnement', 'Créer avis de fractionnement', 0, 4, '2026-06-27 15:48:44', NULL),
(706, 8, 'ordonnancement', 'print_np', 'Imprimer NP', 1, 5, '2026-06-27 15:48:44', NULL),
(707, 8, 'paiements', 'view', 'Voir paiements', 0, 1, '2026-06-27 15:48:44', NULL),
(708, 8, 'paiements', 'add_np', 'Payer NP', 0, 2, '2026-06-27 15:48:44', NULL),
(709, 8, 'paiements', 'add_npf', 'Payer NPF', 0, 3, '2026-06-27 15:48:44', NULL),
(710, 8, 'paiements', 'edit', 'Modifier paiement', 0, 4, '2026-06-27 15:48:44', NULL),
(711, 8, 'paiements', 'print', 'Imprimer reçu', 0, 5, '2026-06-27 15:48:44', NULL),
(712, 8, 'recouvrement', 'view', 'Voir recouvrement', 0, 1, '2026-06-27 15:48:44', NULL),
(713, 8, 'recouvrement', 'apurement', 'Apurer NP / NPF', 0, 2, '2026-06-27 15:48:44', NULL),
(714, 8, 'recouvrement', 'quittance', 'Générer quittance', 0, 3, '2026-06-27 15:48:44', NULL),
(715, 8, 'recouvrement', 'amr', 'Générer AMR', 0, 4, '2026-06-27 15:48:44', NULL),
(716, 8, 'recouvrement', 'print', 'Imprimer documents', 0, 5, '2026-06-27 15:48:45', NULL),
(717, 8, 'apurement', 'view', 'Voir apurements', 0, 1, '2026-06-27 15:48:45', NULL),
(718, 8, 'apurement', 'create', 'Créer apurement', 0, 2, '2026-06-27 15:48:45', NULL),
(719, 8, 'apurement', 'validate', 'Valider apurement', 0, 3, '2026-06-27 15:48:45', NULL),
(720, 8, 'apurement', 'print', 'Imprimer apurement', 0, 4, '2026-06-27 15:48:45', NULL),
(721, 8, 'amr', 'view', 'Voir AMR', 0, 1, '2026-06-27 15:48:45', NULL),
(722, 8, 'amr', 'create', 'Créer AMR', 0, 2, '2026-06-27 15:48:45', NULL),
(723, 8, 'amr', 'print', 'Imprimer AMR', 0, 3, '2026-06-27 15:48:45', NULL),
(724, 8, 'quittances', 'view', 'Voir quittances', 0, 1, '2026-06-27 15:48:46', NULL),
(725, 8, 'quittances', 'create', 'Créer quittance', 0, 2, '2026-06-27 15:48:46', NULL),
(726, 8, 'quittances', 'print', 'Imprimer quittance', 0, 3, '2026-06-27 15:48:46', NULL),
(727, 8, 'penalites', 'view', 'Voir pénalités', 0, 1, '2026-06-27 15:48:47', NULL),
(728, 8, 'penalites', 'manage', 'Gérer barème pénalités', 0, 2, '2026-06-27 15:48:47', NULL),
(729, 8, 'penalites', 'history', 'Voir historique pénalités', 0, 3, '2026-06-27 15:48:47', NULL),
(730, 8, 'inspection', 'view', 'Voir inspection', 0, 1, '2026-06-27 15:48:47', NULL),
(731, 8, 'inspection', 'scan', 'Scanner QR', 0, 2, '2026-06-27 15:48:47', NULL),
(732, 8, 'inspection', 'verify', 'Vérifier document', 0, 3, '2026-06-27 15:48:47', NULL),
(733, 8, 'inspection', 'revoke', 'Révoquer document', 0, 4, '2026-06-27 15:48:47', NULL),
(734, 8, 'inspection', 'fraud', 'Voir fraudes suspectes', 0, 5, '2026-06-27 15:48:47', NULL),
(735, 8, 'inspection', 'alerts', 'Voir alertes', 0, 6, '2026-06-27 15:48:48', NULL),
(736, 8, 'corrections', 'view', 'Voir corrections', 0, 1, '2026-06-27 15:48:48', NULL),
(737, 8, 'corrections', 'create', 'Créer correction', 0, 2, '2026-06-27 15:48:48', NULL),
(738, 8, 'corrections', 'validate', 'Valider correction', 0, 3, '2026-06-27 15:48:48', NULL),
(739, 8, 'corrections', 'history', 'Historique corrections', 0, 4, '2026-06-27 15:48:48', NULL),
(740, 8, 'parametrage', 'view', 'Voir paramètres', 0, 1, '2026-06-27 15:48:48', NULL),
(741, 8, 'parametrage', 'manage', 'Gérer paramètres', 0, 2, '2026-06-27 15:48:48', NULL),
(742, 8, 'parametrage', 'nomenclature', 'Gérer nomenclature', 0, 3, '2026-06-27 15:48:48', NULL),
(743, 8, 'parametrage', 'directions', 'Gérer directions', 0, 4, '2026-06-27 15:48:48', NULL),
(744, 8, 'parametrage', 'services', 'Gérer services', 0, 5, '2026-06-27 15:48:49', NULL),
(745, 8, 'parametrage', 'periodes', 'Gérer périodes', 0, 6, '2026-06-27 15:48:49', NULL),
(746, 8, 'parametrage', 'taux_change', 'Gérer taux change', 0, 7, '2026-06-27 15:48:49', NULL),
(747, 8, 'users', 'view', 'Voir utilisateurs', 0, 1, '2026-06-27 15:48:49', NULL),
(748, 8, 'users', 'add', 'Ajouter utilisateur', 0, 2, '2026-06-27 15:48:49', NULL),
(749, 8, 'users', 'edit', 'Modifier utilisateur', 0, 3, '2026-06-27 15:48:49', NULL),
(750, 8, 'users', 'delete', 'Supprimer utilisateur', 0, 4, '2026-06-27 15:48:49', NULL),
(751, 8, 'users', 'status', 'Activer / désactiver utilisateur', 0, 5, '2026-06-27 15:48:49', NULL),
(752, 8, 'users', 'password', 'Changer mot de passe', 0, 6, '2026-06-27 15:48:50', NULL),
(753, 8, 'roles', 'view', 'Voir rôles', 0, 1, '2026-06-27 15:48:50', NULL),
(754, 8, 'roles', 'add', 'Ajouter rôle', 0, 2, '2026-06-27 15:48:50', NULL),
(755, 8, 'roles', 'edit', 'Modifier rôle', 0, 3, '2026-06-27 15:48:50', NULL),
(756, 8, 'roles', 'delete', 'Supprimer rôle', 0, 4, '2026-06-27 15:48:50', NULL),
(757, 8, 'roles', 'permissions', 'Gérer permissions', 0, 5, '2026-06-27 15:48:50', NULL),
(758, 8, 'administration', 'view', 'Voir administration', 0, 1, '2026-06-27 15:48:50', NULL),
(759, 8, 'administration', 'logs', 'Voir journaux', 0, 2, '2026-06-27 15:48:50', NULL),
(760, 8, 'administration', 'backup', 'Sauvegardes', 0, 3, '2026-06-27 15:48:50', NULL),
(761, 8, 'administration', 'settings', 'Paramètres système', 0, 4, '2026-06-27 15:48:50', NULL),
(762, 8, 'pwa', 'view', 'Voir PWA', 0, 1, '2026-06-27 15:48:50', NULL),
(763, 8, 'pwa', 'sync', 'Synchronisation PWA', 0, 2, '2026-06-27 15:48:50', NULL),
(764, 8, 'pwa', 'backup', 'Sauvegarde PWA', 0, 3, '2026-06-27 15:48:51', NULL),
(765, 8, 'pwa', 'agents', 'Agents terrain', 0, 4, '2026-06-27 15:48:51', NULL),
(766, 8, 'pwa', 'reports', 'Rapports PWA', 0, 5, '2026-06-27 15:48:51', NULL),
(767, 11, 'dashboard', 'view', 'Voir le tableau de bord', 1, 1, '2026-06-27 16:07:31', '2026-06-27 18:17:11'),
(768, 11, 'contribuables', 'view', 'Voir contribuables', 0, 1, '2026-06-27 16:07:31', '2026-06-27 18:17:11'),
(769, 11, 'contribuables', 'add', 'Ajouter contribuable', 0, 2, '2026-06-27 16:07:31', '2026-06-27 18:17:11'),
(770, 11, 'contribuables', 'edit', 'Modifier contribuable', 0, 3, '2026-06-27 16:07:31', '2026-06-27 18:17:11'),
(771, 11, 'contribuables', 'delete', 'Supprimer contribuable', 0, 4, '2026-06-27 16:07:31', '2026-06-27 18:17:11'),
(772, 11, 'contribuables', 'import', 'Importer contribuables', 0, 5, '2026-06-27 16:07:31', '2026-06-27 18:17:11'),
(773, 11, 'constatation', 'view', 'Voir NT', 0, 1, '2026-06-27 16:07:32', '2026-06-27 18:17:11'),
(774, 11, 'constatation', 'add', 'Créer NT', 0, 2, '2026-06-27 16:07:32', '2026-06-27 18:17:11'),
(775, 11, 'constatation', 'edit', 'Modifier NT', 0, 3, '2026-06-27 16:07:32', '2026-06-27 18:17:11'),
(776, 11, 'constatation', 'delete', 'Supprimer NT', 0, 4, '2026-06-27 16:07:32', '2026-06-27 18:17:12'),
(777, 11, 'constatation', 'submit', 'Soumettre NT', 0, 5, '2026-06-27 16:07:32', '2026-06-27 18:17:12'),
(778, 11, 'constatation', 'print', 'Imprimer NT', 0, 6, '2026-06-27 16:07:32', '2026-06-27 18:17:12'),
(779, 11, 'liquidation', 'view', 'Voir liquidation', 0, 1, '2026-06-27 16:07:32', '2026-06-27 18:17:12'),
(780, 11, 'liquidation', 'create_nd', 'Créer ND', 0, 2, '2026-06-27 16:07:32', '2026-06-27 18:17:12'),
(781, 11, 'liquidation', 'validate_nd', 'Valider ND', 0, 3, '2026-06-27 16:07:32', '2026-06-27 18:17:12'),
(782, 11, 'liquidation', 'reject_nd', 'Rejeter ND', 0, 4, '2026-06-27 16:07:32', '2026-06-27 18:17:12'),
(783, 11, 'liquidation', 'print_nd', 'Imprimer ND', 0, 5, '2026-06-27 16:07:32', '2026-06-27 18:17:12'),
(784, 11, 'controle', 'view', 'Voir contrôle', 0, 1, '2026-06-27 16:07:33', '2026-06-27 18:17:12'),
(785, 11, 'controle', 'validate', 'Valider contrôle', 0, 2, '2026-06-27 16:07:33', '2026-06-27 18:17:12'),
(786, 11, 'controle', 'reject', 'Rejeter contrôle', 0, 3, '2026-06-27 16:07:33', '2026-06-27 18:17:13'),
(787, 11, 'controle', 'observe', 'Ajouter observation', 0, 4, '2026-06-27 16:07:33', '2026-06-27 18:17:13'),
(788, 11, 'ordonnancement', 'view', 'Voir ordonnancement', 0, 1, '2026-06-27 16:07:33', '2026-06-27 18:17:13'),
(789, 11, 'ordonnancement', 'create_np', 'Créer NP', 0, 2, '2026-06-27 16:07:33', '2026-06-27 18:17:13'),
(790, 11, 'ordonnancement', 'fractionner_np', 'Fractionner NP', 0, 3, '2026-06-27 16:07:33', '2026-06-27 18:17:13'),
(791, 11, 'ordonnancement', 'avis_fractionnement', 'Créer avis de fractionnement', 0, 4, '2026-06-27 16:07:33', '2026-06-27 18:17:13'),
(792, 11, 'ordonnancement', 'print_np', 'Imprimer NP', 0, 5, '2026-06-27 16:07:33', '2026-06-27 18:17:13'),
(793, 11, 'paiements', 'view', 'Voir paiements', 1, 1, '2026-06-27 16:07:33', '2026-06-27 18:17:13'),
(794, 11, 'paiements', 'add_np', 'Payer NP', 1, 2, '2026-06-27 16:07:33', '2026-06-27 18:17:13'),
(795, 11, 'paiements', 'add_npf', 'Payer NPF', 1, 3, '2026-06-27 16:07:33', '2026-06-27 18:17:13'),
(796, 11, 'paiements', 'edit', 'Modifier paiement', 0, 4, '2026-06-27 16:07:34', '2026-06-27 18:17:13'),
(797, 11, 'paiements', 'print', 'Imprimer reçu', 1, 5, '2026-06-27 16:07:34', '2026-06-27 18:17:13'),
(798, 11, 'recouvrement', 'view', 'Voir recouvrement', 0, 1, '2026-06-27 16:07:34', '2026-06-27 18:17:13'),
(799, 11, 'recouvrement', 'apurement', 'Apurer NP / NPF', 0, 2, '2026-06-27 16:07:34', '2026-06-27 18:17:14'),
(800, 11, 'recouvrement', 'quittance', 'Générer quittance', 0, 3, '2026-06-27 16:07:34', '2026-06-27 18:17:14'),
(801, 11, 'recouvrement', 'amr', 'Générer AMR', 0, 4, '2026-06-27 16:07:34', '2026-06-27 18:17:14'),
(802, 11, 'recouvrement', 'print', 'Imprimer documents', 0, 5, '2026-06-27 16:07:34', '2026-06-27 18:17:14'),
(803, 11, 'apurement', 'view', 'Voir apurements', 0, 1, '2026-06-27 16:07:34', '2026-06-27 18:17:14'),
(804, 11, 'apurement', 'create', 'Créer apurement', 0, 2, '2026-06-27 16:07:34', '2026-06-27 18:17:14'),
(805, 11, 'apurement', 'validate', 'Valider apurement', 0, 3, '2026-06-27 16:07:34', '2026-06-27 18:17:14'),
(806, 11, 'apurement', 'print', 'Imprimer apurement', 0, 4, '2026-06-27 16:07:34', '2026-06-27 18:17:14'),
(807, 11, 'amr', 'view', 'Voir AMR', 1, 1, '2026-06-27 16:07:34', '2026-06-27 18:17:14'),
(808, 11, 'amr', 'create', 'Créer AMR', 0, 2, '2026-06-27 16:07:35', '2026-06-27 18:17:14'),
(809, 11, 'amr', 'print', 'Imprimer AMR', 1, 3, '2026-06-27 16:07:35', '2026-06-27 18:17:14'),
(810, 11, 'quittances', 'view', 'Voir quittances', 0, 1, '2026-06-27 16:07:35', '2026-06-27 18:17:15'),
(811, 11, 'quittances', 'create', 'Créer quittance', 0, 2, '2026-06-27 16:07:35', '2026-06-27 18:17:15'),
(812, 11, 'quittances', 'print', 'Imprimer quittance', 0, 3, '2026-06-27 16:07:35', '2026-06-27 18:17:15'),
(813, 11, 'penalites', 'view', 'Voir pénalités', 0, 1, '2026-06-27 16:07:35', '2026-06-27 18:17:15'),
(814, 11, 'penalites', 'manage', 'Gérer barème pénalités', 0, 2, '2026-06-27 16:07:35', '2026-06-27 18:17:15'),
(815, 11, 'penalites', 'history', 'Voir historique pénalités', 0, 3, '2026-06-27 16:07:35', '2026-06-27 18:17:15'),
(816, 11, 'inspection', 'view', 'Voir inspection', 0, 1, '2026-06-27 16:07:35', '2026-06-27 18:17:15'),
(817, 11, 'inspection', 'scan', 'Scanner QR', 0, 2, '2026-06-27 16:07:35', '2026-06-27 18:17:15'),
(818, 11, 'inspection', 'verify', 'Vérifier document', 0, 3, '2026-06-27 16:07:35', '2026-06-27 18:17:15'),
(819, 11, 'inspection', 'revoke', 'Révoquer document', 0, 4, '2026-06-27 16:07:35', '2026-06-27 18:17:15'),
(820, 11, 'inspection', 'fraud', 'Voir fraudes suspectes', 0, 5, '2026-06-27 16:07:36', '2026-06-27 18:17:15'),
(821, 11, 'inspection', 'alerts', 'Voir alertes', 0, 6, '2026-06-27 16:07:36', '2026-06-27 18:17:15'),
(822, 11, 'corrections', 'view', 'Voir corrections', 0, 1, '2026-06-27 16:07:36', '2026-06-27 18:17:16'),
(823, 11, 'corrections', 'create', 'Créer correction', 0, 2, '2026-06-27 16:07:36', '2026-06-27 18:17:16'),
(824, 11, 'corrections', 'validate', 'Valider correction', 0, 3, '2026-06-27 16:07:36', '2026-06-27 18:17:16'),
(825, 11, 'corrections', 'history', 'Historique corrections', 0, 4, '2026-06-27 16:07:36', '2026-06-27 18:17:16'),
(826, 11, 'parametrage', 'view', 'Voir paramètres', 0, 1, '2026-06-27 16:07:36', '2026-06-27 18:17:16'),
(827, 11, 'parametrage', 'manage', 'Gérer paramètres', 0, 2, '2026-06-27 16:07:36', '2026-06-27 18:17:16'),
(828, 11, 'parametrage', 'nomenclature', 'Gérer nomenclature', 0, 3, '2026-06-27 16:07:36', '2026-06-27 18:17:16'),
(829, 11, 'parametrage', 'directions', 'Gérer directions', 0, 4, '2026-06-27 16:07:36', '2026-06-27 18:17:16'),
(830, 11, 'parametrage', 'services', 'Gérer services', 0, 5, '2026-06-27 16:07:36', '2026-06-27 18:17:16'),
(831, 11, 'parametrage', 'periodes', 'Gérer périodes', 0, 6, '2026-06-27 16:07:36', '2026-06-27 18:17:16'),
(832, 11, 'parametrage', 'taux_change', 'Gérer taux change', 0, 7, '2026-06-27 16:07:37', '2026-06-27 18:17:16'),
(833, 11, 'users', 'view', 'Voir utilisateurs', 0, 1, '2026-06-27 16:07:37', '2026-06-27 18:17:16'),
(834, 11, 'users', 'add', 'Ajouter utilisateur', 0, 2, '2026-06-27 16:07:37', '2026-06-27 18:17:17'),
(835, 11, 'users', 'edit', 'Modifier utilisateur', 0, 3, '2026-06-27 16:07:37', '2026-06-27 18:17:17'),
(836, 11, 'users', 'delete', 'Supprimer utilisateur', 0, 4, '2026-06-27 16:07:37', '2026-06-27 18:17:17'),
(837, 11, 'users', 'status', 'Activer / désactiver utilisateur', 0, 5, '2026-06-27 16:07:37', '2026-06-27 18:17:17'),
(838, 11, 'users', 'password', 'Changer mot de passe', 0, 6, '2026-06-27 16:07:37', '2026-06-27 18:17:18'),
(839, 11, 'roles', 'view', 'Voir rôles', 0, 1, '2026-06-27 16:07:37', '2026-06-27 18:17:18'),
(840, 11, 'roles', 'add', 'Ajouter rôle', 0, 2, '2026-06-27 16:07:37', '2026-06-27 18:17:18'),
(841, 11, 'roles', 'edit', 'Modifier rôle', 0, 3, '2026-06-27 16:07:37', '2026-06-27 18:17:18'),
(842, 11, 'roles', 'delete', 'Supprimer rôle', 0, 4, '2026-06-27 16:07:37', '2026-06-27 18:17:18'),
(843, 11, 'roles', 'permissions', 'Gérer permissions', 0, 5, '2026-06-27 16:07:37', '2026-06-27 18:17:18'),
(844, 11, 'administration', 'view', 'Voir administration', 0, 1, '2026-06-27 16:07:38', '2026-06-27 18:17:18'),
(845, 11, 'administration', 'logs', 'Voir journaux', 0, 2, '2026-06-27 16:07:38', '2026-06-27 18:17:18'),
(846, 11, 'administration', 'backup', 'Sauvegardes', 0, 3, '2026-06-27 16:07:38', '2026-06-27 18:17:18'),
(847, 11, 'administration', 'settings', 'Paramètres système', 0, 4, '2026-06-27 16:07:38', '2026-06-27 18:17:18'),
(848, 11, 'pwa', 'view', 'Voir PWA', 0, 1, '2026-06-27 16:07:38', '2026-06-27 18:17:18'),
(849, 11, 'pwa', 'sync', 'Synchronisation PWA', 0, 2, '2026-06-27 16:07:38', '2026-06-27 18:17:18'),
(850, 11, 'pwa', 'backup', 'Sauvegarde PWA', 0, 3, '2026-06-27 16:07:38', '2026-06-27 18:17:19'),
(851, 11, 'pwa', 'agents', 'Agents terrain', 0, 4, '2026-06-27 16:07:38', '2026-06-27 18:17:19'),
(852, 11, 'pwa', 'reports', 'Rapports PWA', 0, 5, '2026-06-27 16:07:38', '2026-06-27 18:17:19'),
(853, 11, 'amr', 'pay', NULL, 1, 0, '2026-06-27 16:26:44', NULL),
(859, 14, 'amr', 'pay', NULL, 1, 0, '2026-06-27 17:02:33', NULL),
(873, 15, 'dashboard', 'view', 'Voir le tableau de bord', 1, 1, '2026-06-27 17:10:52', '2026-06-27 19:34:39'),
(874, 15, 'contribuables', 'view', 'Voir contribuables', 0, 1, '2026-06-27 17:10:53', '2026-06-27 19:34:39'),
(875, 15, 'contribuables', 'add', 'Ajouter contribuable', 0, 2, '2026-06-27 17:10:53', '2026-06-27 19:34:39'),
(876, 15, 'contribuables', 'edit', 'Modifier contribuable', 0, 3, '2026-06-27 17:10:53', '2026-06-27 19:34:39'),
(877, 15, 'contribuables', 'delete', 'Supprimer contribuable', 0, 4, '2026-06-27 17:10:53', '2026-06-27 19:34:39'),
(878, 15, 'contribuables', 'import', 'Importer contribuables', 0, 5, '2026-06-27 17:10:53', '2026-06-27 19:34:39'),
(879, 15, 'constatation', 'view', 'Voir NT', 0, 1, '2026-06-27 17:10:53', '2026-06-27 19:34:40'),
(880, 15, 'constatation', 'add', 'Créer NT', 0, 2, '2026-06-27 17:10:53', '2026-06-27 19:34:40'),
(881, 15, 'constatation', 'edit', 'Modifier NT', 0, 3, '2026-06-27 17:10:53', '2026-06-27 19:34:40'),
(882, 15, 'constatation', 'delete', 'Supprimer NT', 0, 4, '2026-06-27 17:10:54', '2026-06-27 19:34:40'),
(883, 15, 'constatation', 'submit', 'Soumettre NT', 0, 5, '2026-06-27 17:10:54', '2026-06-27 19:34:40'),
(884, 15, 'constatation', 'print', 'Imprimer NT', 0, 6, '2026-06-27 17:10:54', '2026-06-27 19:34:40'),
(885, 15, 'liquidation', 'view', 'Voir liquidation', 0, 1, '2026-06-27 17:10:54', '2026-06-27 19:34:40'),
(886, 15, 'liquidation', 'create_nd', 'Créer ND', 0, 2, '2026-06-27 17:10:54', '2026-06-27 19:34:40'),
(887, 15, 'liquidation', 'validate_nd', 'Valider ND', 0, 3, '2026-06-27 17:10:54', '2026-06-27 19:34:40'),
(888, 15, 'liquidation', 'reject_nd', 'Rejeter ND', 0, 4, '2026-06-27 17:10:54', '2026-06-27 19:34:40'),
(889, 15, 'liquidation', 'print_nd', 'Imprimer ND', 0, 5, '2026-06-27 17:10:54', '2026-06-27 19:34:40'),
(890, 15, 'controle', 'view', 'Voir contrôle', 0, 1, '2026-06-27 17:10:54', '2026-06-27 19:34:41'),
(891, 15, 'controle', 'validate', 'Valider contrôle', 0, 2, '2026-06-27 17:10:55', '2026-06-27 19:34:41'),
(892, 15, 'controle', 'reject', 'Rejeter contrôle', 0, 3, '2026-06-27 17:10:55', '2026-06-27 19:34:41'),
(893, 15, 'controle', 'observe', 'Ajouter observation', 0, 4, '2026-06-27 17:10:55', '2026-06-27 19:34:41'),
(894, 15, 'ordonnancement', 'view', 'Voir ordonnancement', 0, 1, '2026-06-27 17:10:55', '2026-06-27 19:34:41'),
(895, 15, 'ordonnancement', 'create_np', 'Créer NP', 0, 2, '2026-06-27 17:10:55', '2026-06-27 19:34:41'),
(896, 15, 'ordonnancement', 'fractionner_np', 'Fractionner NP', 0, 3, '2026-06-27 17:10:55', '2026-06-27 19:34:41'),
(897, 15, 'ordonnancement', 'avis_fractionnement', 'Créer avis de fractionnement', 0, 4, '2026-06-27 17:10:55', '2026-06-27 19:34:41'),
(898, 15, 'ordonnancement', 'print_np', 'Imprimer NP', 0, 5, '2026-06-27 17:10:55', '2026-06-27 19:34:41'),
(899, 15, 'paiements', 'view', 'Voir paiements', 0, 1, '2026-06-27 17:10:55', '2026-06-27 19:34:41'),
(900, 15, 'paiements', 'add_np', 'Payer NP', 0, 2, '2026-06-27 17:10:55', '2026-06-27 19:34:41'),
(901, 15, 'paiements', 'add_npf', 'Payer NPF', 0, 3, '2026-06-27 17:10:55', '2026-06-27 19:34:42'),
(902, 15, 'paiements', 'edit', 'Modifier paiement', 0, 4, '2026-06-27 17:10:56', '2026-06-27 19:34:42'),
(903, 15, 'paiements', 'print', 'Imprimer reçu', 0, 5, '2026-06-27 17:10:56', '2026-06-27 19:34:42'),
(904, 15, 'recouvrement', 'view', 'Voir recouvrement', 0, 1, '2026-06-27 17:10:56', '2026-06-27 19:34:42'),
(905, 15, 'recouvrement', 'apurement', 'Apurer NP / NPF', 0, 2, '2026-06-27 17:10:56', '2026-06-27 19:34:42'),
(906, 15, 'recouvrement', 'quittance', 'Générer quittance', 0, 3, '2026-06-27 17:10:56', '2026-06-27 19:34:42'),
(907, 15, 'recouvrement', 'amr', 'Générer AMR', 0, 4, '2026-06-27 17:10:56', '2026-06-27 19:34:42'),
(908, 15, 'recouvrement', 'print', 'Imprimer documents', 0, 5, '2026-06-27 17:10:56', '2026-06-27 19:34:42'),
(909, 15, 'apurement', 'view', 'Voir apurements', 1, 1, '2026-06-27 17:10:56', '2026-06-27 19:34:42'),
(910, 15, 'apurement', 'create', 'Créer apurement', 1, 2, '2026-06-27 17:10:56', '2026-06-27 19:34:43'),
(911, 15, 'apurement', 'validate', 'Valider apurement', 0, 3, '2026-06-27 17:10:56', '2026-06-27 19:34:43'),
(912, 15, 'apurement', 'print', 'Imprimer apurement', 0, 4, '2026-06-27 17:10:56', '2026-06-27 19:34:43'),
(913, 15, 'amr', 'view', 'Voir AMR', 0, 1, '2026-06-27 17:10:56', '2026-06-27 19:34:43'),
(914, 15, 'amr', 'create', 'Créer AMR', 0, 2, '2026-06-27 17:10:57', '2026-06-27 19:34:43'),
(915, 15, 'amr', 'print', 'Imprimer AMR', 0, 3, '2026-06-27 17:10:57', '2026-06-27 19:34:43'),
(916, 15, 'quittances', 'view', 'Voir quittances', 1, 1, '2026-06-27 17:10:57', '2026-06-27 19:34:43'),
(917, 15, 'quittances', 'create', 'Créer quittance', 1, 2, '2026-06-27 17:10:57', '2026-06-27 19:34:43'),
(918, 15, 'quittances', 'print', 'Imprimer quittance', 1, 3, '2026-06-27 17:10:57', '2026-06-27 19:34:44'),
(919, 15, 'penalites', 'view', 'Voir pénalités', 0, 1, '2026-06-27 17:10:57', '2026-06-27 19:34:44'),
(920, 15, 'penalites', 'manage', 'Gérer barème pénalités', 0, 2, '2026-06-27 17:10:57', '2026-06-27 19:34:44'),
(921, 15, 'penalites', 'history', 'Voir historique pénalités', 0, 3, '2026-06-27 17:10:57', '2026-06-27 19:34:44'),
(922, 15, 'inspection', 'view', 'Voir inspection', 0, 1, '2026-06-27 17:10:57', '2026-06-27 19:34:44'),
(923, 15, 'inspection', 'scan', 'Scanner QR', 0, 2, '2026-06-27 17:10:57', '2026-06-27 19:34:44'),
(924, 15, 'inspection', 'verify', 'Vérifier document', 0, 3, '2026-06-27 17:10:57', '2026-06-27 19:34:44'),
(925, 15, 'inspection', 'revoke', 'Révoquer document', 0, 4, '2026-06-27 17:10:58', '2026-06-27 19:34:44'),
(926, 15, 'inspection', 'fraud', 'Voir fraudes suspectes', 0, 5, '2026-06-27 17:10:58', '2026-06-27 19:34:45'),
(927, 15, 'inspection', 'alerts', 'Voir alertes', 0, 6, '2026-06-27 17:10:58', '2026-06-27 19:34:45'),
(928, 15, 'corrections', 'view', 'Voir corrections', 0, 1, '2026-06-27 17:10:58', '2026-06-27 19:34:45'),
(929, 15, 'corrections', 'create', 'Créer correction', 0, 2, '2026-06-27 17:10:58', '2026-06-27 19:34:45'),
(930, 15, 'corrections', 'validate', 'Valider correction', 0, 3, '2026-06-27 17:10:58', '2026-06-27 19:34:45'),
(931, 15, 'corrections', 'history', 'Historique corrections', 0, 4, '2026-06-27 17:10:58', '2026-06-27 19:34:45'),
(932, 15, 'parametrage', 'view', 'Voir paramètres', 0, 1, '2026-06-27 17:10:58', '2026-06-27 19:34:45'),
(933, 15, 'parametrage', 'manage', 'Gérer paramètres', 0, 2, '2026-06-27 17:10:58', '2026-06-27 19:34:45'),
(934, 15, 'parametrage', 'nomenclature', 'Gérer nomenclature', 0, 3, '2026-06-27 17:10:58', '2026-06-27 19:34:45'),
(935, 15, 'parametrage', 'directions', 'Gérer directions', 0, 4, '2026-06-27 17:10:58', '2026-06-27 19:34:45'),
(936, 15, 'parametrage', 'services', 'Gérer services', 0, 5, '2026-06-27 17:10:58', '2026-06-27 19:34:45'),
(937, 15, 'parametrage', 'periodes', 'Gérer périodes', 0, 6, '2026-06-27 17:10:58', '2026-06-27 19:34:45'),
(938, 15, 'parametrage', 'taux_change', 'Gérer taux change', 0, 7, '2026-06-27 17:10:58', '2026-06-27 19:34:46'),
(939, 15, 'users', 'view', 'Voir utilisateurs', 0, 1, '2026-06-27 17:10:59', '2026-06-27 19:34:46'),
(940, 15, 'users', 'add', 'Ajouter utilisateur', 0, 2, '2026-06-27 17:10:59', '2026-06-27 19:34:46'),
(941, 15, 'users', 'edit', 'Modifier utilisateur', 0, 3, '2026-06-27 17:10:59', '2026-06-27 19:34:46'),
(942, 15, 'users', 'delete', 'Supprimer utilisateur', 0, 4, '2026-06-27 17:10:59', '2026-06-27 19:34:46'),
(943, 15, 'users', 'status', 'Activer / désactiver utilisateur', 0, 5, '2026-06-27 17:10:59', '2026-06-27 19:34:46'),
(944, 15, 'users', 'password', 'Changer mot de passe', 0, 6, '2026-06-27 17:10:59', '2026-06-27 19:34:46'),
(945, 15, 'roles', 'view', 'Voir rôles', 0, 1, '2026-06-27 17:10:59', '2026-06-27 19:34:46'),
(946, 15, 'roles', 'add', 'Ajouter rôle', 0, 2, '2026-06-27 17:10:59', '2026-06-27 19:34:46'),
(947, 15, 'roles', 'edit', 'Modifier rôle', 0, 3, '2026-06-27 17:10:59', '2026-06-27 19:34:46'),
(948, 15, 'roles', 'delete', 'Supprimer rôle', 0, 4, '2026-06-27 17:10:59', '2026-06-27 19:34:46'),
(949, 15, 'roles', 'permissions', 'Gérer permissions', 0, 5, '2026-06-27 17:10:59', '2026-06-27 19:34:47'),
(950, 15, 'administration', 'view', 'Voir administration', 0, 1, '2026-06-27 17:10:59', '2026-06-27 19:34:47'),
(951, 15, 'administration', 'logs', 'Voir journaux', 0, 2, '2026-06-27 17:10:59', '2026-06-27 19:34:47'),
(952, 15, 'administration', 'backup', 'Sauvegardes', 0, 3, '2026-06-27 17:10:59', '2026-06-27 19:34:47'),
(953, 15, 'administration', 'settings', 'Paramètres système', 0, 4, '2026-06-27 17:11:00', '2026-06-27 19:34:47'),
(954, 15, 'pwa', 'view', 'Voir PWA', 0, 1, '2026-06-27 17:11:00', '2026-06-27 19:34:47'),
(955, 15, 'pwa', 'sync', 'Synchronisation PWA', 0, 2, '2026-06-27 17:11:00', '2026-06-27 19:34:47'),
(956, 15, 'pwa', 'backup', 'Sauvegarde PWA', 0, 3, '2026-06-27 17:11:00', '2026-06-27 19:34:47'),
(957, 15, 'pwa', 'agents', 'Agents terrain', 0, 4, '2026-06-27 17:11:00', '2026-06-27 19:34:47'),
(958, 15, 'pwa', 'reports', 'Rapports PWA', 0, 5, '2026-06-27 17:11:00', '2026-06-27 19:34:47'),
(1089, 10, 'dashboard', 'view', 'Voir le tableau de bord', 1, 1, '2026-06-27 20:58:16', NULL),
(1090, 10, 'ordonnancement', 'avis_fractionnement', 'Créer avis de fractionnement', 1, 2, '2026-06-27 20:58:16', NULL),
(1091, 10, 'recouvrement', 'view', 'Voir recouvrement', 1, 3, '2026-06-27 20:58:16', NULL),
(1092, 10, 'amr', 'view', 'Voir AMR', 1, 4, '2026-06-27 20:58:16', NULL),
(1093, 10, 'amr', 'create', 'Créer AMR', 1, 5, '2026-06-27 20:58:16', NULL),
(1094, 10, 'amr', 'print', 'Imprimer AMR', 1, 6, '2026-06-27 20:58:16', NULL),
(1095, 10, 'penalites', 'view', 'Voir pénalités', 1, 7, '2026-06-27 20:58:16', NULL),
(1096, 10, 'penalites', 'manage', 'Gérer barème pénalités', 1, 8, '2026-06-27 20:58:16', NULL),
(1097, 10, 'penalites', 'history', 'Voir historique pénalités', 1, 9, '2026-06-27 20:58:16', NULL),
(1098, 10, 'inspection', 'fraud', 'Voir fraudes suspectes', 1, 10, '2026-06-27 20:58:16', NULL),
(1099, 10, 'inspection', 'alerts', 'Voir alertes', 1, 11, '2026-06-27 20:58:16', NULL),
(1100, 10, 'parametrage', 'taux_change', 'Gérer taux de change', 1, 12, '2026-06-27 20:58:16', NULL),
(1101, 10, 'administration', 'logs', 'Voir journaux', 1, 13, '2026-06-27 20:58:16', NULL),
(1102, 10, 'administration', 'backup', 'Sauvegardes', 1, 14, '2026-06-27 20:58:16', NULL),
(1103, 10, 'inspection', 'verify', 'Voir journal des vérifications QR', 1, 15, '2026-06-29 16:08:25', NULL),
(1119, 12, 'dashboard', 'view', 'Voir tableau de bord', 1, 1, '2026-06-29 16:58:45', NULL),
(1120, 12, 'inspection', 'view', 'Voir tableau inspection', 1, 2, '2026-06-29 16:58:45', NULL),
(1121, 12, 'inspection', 'scan', 'Scanner QR Code', 1, 3, '2026-06-29 16:58:45', NULL),
(1122, 12, 'inspection', 'verify', 'Voir journal vérifications QR', 1, 4, '2026-06-29 16:58:45', NULL),
(1123, 12, 'inspection', 'fraud', 'Voir fraudes suspectes', 1, 5, '2026-06-29 16:58:45', NULL),
(1124, 12, 'inspection', 'alerts', 'Voir alertes inspection', 1, 6, '2026-06-29 16:58:45', NULL),
(1125, 12, 'inspection', 'revoke', 'Voir documents révoqués', 1, 7, '2026-06-29 16:58:45', NULL),
(1126, 12, 'consultation', 'nt', 'Consulter NT seulement', 1, 8, '2026-06-29 16:58:45', NULL),
(1127, 12, 'consultation', 'nd', 'Consulter ND seulement', 1, 9, '2026-06-29 16:58:45', NULL),
(1128, 12, 'consultation', 'np', 'Consulter NP / NPF seulement', 1, 10, '2026-06-29 16:58:45', NULL),
(1129, 12, 'consultation', 'paiements', 'Consulter paiements seulement', 1, 11, '2026-06-29 16:58:45', NULL),
(1130, 12, 'consultation', 'amr', 'Consulter AMR seulement', 1, 12, '2026-06-29 16:58:45', NULL),
(1131, 12, 'consultation', 'apurements', 'Consulter apurements seulement', 1, 13, '2026-06-29 16:58:45', NULL),
(1132, 12, 'consultation', 'quittances', 'Consulter quittances seulement', 1, 14, '2026-06-29 16:58:45', NULL),
(1133, 13, 'dashboard', 'view', 'Voir tableau de bord', 1, 1, '2026-06-29 17:13:03', NULL),
(1134, 13, 'inspection', 'view', 'Voir tableau inspection', 1, 2, '2026-06-29 17:13:03', NULL),
(1135, 13, 'inspection', 'verify', 'Voir journal vérifications QR', 1, 3, '2026-06-29 17:13:03', NULL),
(1136, 13, 'inspection', 'fraud', 'Voir fraudes suspectes', 1, 4, '2026-06-29 17:13:03', NULL),
(1137, 13, 'inspection', 'alerts', 'Voir alertes inspection', 1, 5, '2026-06-29 17:13:03', NULL),
(1138, 13, 'administration', 'logs', 'Voir journaux système', 1, 6, '2026-06-29 17:13:03', NULL),
(1139, 13, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 17:13:03', '2026-06-30 01:25:51'),
(1185, 1, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:09:54', '2026-06-30 01:25:51'),
(1186, 1, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:09:54', '2026-06-30 01:25:51'),
(1187, 1, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:09:54', '2026-06-30 01:25:51'),
(1188, 1, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:09:54', '2026-06-30 01:25:51'),
(1189, 1, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:09:54', '2026-06-30 01:25:51'),
(1190, 1, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:09:54', '2026-06-30 01:25:51'),
(1191, 1, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:09:54', '2026-06-30 01:25:51'),
(1192, 1, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:09:54', '2026-06-30 01:25:51'),
(1193, 1, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:09:54', '2026-06-30 01:25:51'),
(1194, 1, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:09:54', '2026-06-30 01:25:51'),
(1195, 1, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:09:54', '2026-06-30 01:25:51'),
(1196, 1, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:09:54', '2026-06-30 01:25:51'),
(1198, 2, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1199, 2, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1200, 2, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1201, 2, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1202, 2, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1203, 2, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1204, 2, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1205, 2, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1206, 2, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1207, 2, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1208, 2, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1209, 2, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1210, 3, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1211, 3, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1212, 3, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1213, 3, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1214, 3, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1215, 3, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1216, 3, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1217, 3, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1218, 3, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1219, 3, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1220, 3, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1221, 3, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1222, 4, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1223, 4, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1224, 4, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1225, 4, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1226, 4, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1227, 4, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1228, 4, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1229, 4, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1230, 4, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1231, 4, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1232, 4, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1233, 4, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1234, 5, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1235, 5, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1236, 5, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1237, 5, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1238, 5, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1239, 5, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1240, 5, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1241, 5, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1242, 5, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1243, 5, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1244, 5, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1245, 5, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1246, 6, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1247, 6, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1248, 6, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1249, 6, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1250, 6, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1251, 6, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1252, 6, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1253, 6, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1254, 6, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1255, 6, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1256, 6, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1257, 6, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1258, 7, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1259, 7, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1260, 7, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1261, 7, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1262, 7, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1263, 7, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1264, 7, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1265, 7, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1266, 7, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1267, 7, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1268, 7, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1269, 7, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1270, 8, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1271, 8, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1272, 8, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1273, 8, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1274, 8, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1275, 8, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1276, 8, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1277, 8, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1278, 8, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1279, 8, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1280, 8, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1281, 8, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1282, 9, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1283, 9, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL);
INSERT INTO `permissions` (`id`, `role_id`, `module`, `action`, `description`, `autorise`, `ordre`, `created_at`, `updated_at`) VALUES
(1284, 9, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1285, 9, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1286, 9, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1287, 9, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1288, 9, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1289, 9, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1290, 9, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1291, 9, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1292, 9, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1293, 9, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1294, 10, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1295, 10, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1296, 10, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1297, 10, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1298, 10, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1299, 10, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1300, 10, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1301, 10, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1302, 10, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1303, 10, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1304, 10, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1305, 10, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1306, 11, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1307, 11, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1308, 11, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1309, 11, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1310, 11, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1311, 11, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1312, 11, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1313, 11, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1314, 11, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1315, 11, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1316, 11, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1317, 11, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1318, 12, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1319, 12, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1320, 12, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1321, 12, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1322, 12, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1323, 12, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1324, 12, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1325, 12, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1326, 12, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1327, 12, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1328, 12, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1329, 12, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1330, 13, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1331, 13, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1332, 13, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1333, 13, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1334, 13, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1335, 13, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1336, 13, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1337, 13, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1338, 13, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1339, 13, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1340, 13, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1341, 14, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1342, 14, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1343, 14, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1344, 14, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1345, 14, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1346, 14, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1347, 14, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1348, 14, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1349, 14, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1350, 14, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1351, 14, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1352, 14, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1353, 15, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1354, 15, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1355, 15, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1356, 15, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1357, 15, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1358, 15, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1359, 15, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1360, 15, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1361, 15, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1362, 15, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1363, 15, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1364, 15, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1365, 16, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1366, 16, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1367, 16, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1368, 16, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1369, 16, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1370, 16, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1371, 16, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1372, 16, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1373, 16, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1374, 16, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1375, 16, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1376, 16, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1377, 17, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1378, 17, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1379, 17, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1380, 17, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1381, 17, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1382, 17, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1383, 17, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1384, 17, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1385, 17, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1386, 17, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1387, 17, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1388, 17, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1389, 18, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1390, 18, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1391, 18, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1392, 18, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1393, 18, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1394, 18, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1395, 18, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1396, 18, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1397, 18, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1398, 18, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1399, 18, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1400, 18, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1401, 19, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1402, 19, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1403, 19, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1404, 19, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1405, 19, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1406, 19, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1407, 19, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1408, 19, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1409, 19, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1410, 19, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1411, 19, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1412, 19, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL),
(1413, 20, 'rapports', 'view', 'Voir les rapports', 1, 1, '2026-06-29 23:25:51', NULL),
(1414, 20, 'rapports', 'nt', 'Rapport Notes de Taxation', 1, 2, '2026-06-29 23:25:51', NULL),
(1415, 20, 'rapports', 'nd', 'Rapport Notes de Débit', 1, 3, '2026-06-29 23:25:51', NULL),
(1416, 20, 'rapports', 'np', 'Rapport Notes de Perception', 1, 4, '2026-06-29 23:25:51', NULL),
(1417, 20, 'rapports', 'amr', 'Rapport AMR', 1, 5, '2026-06-29 23:25:51', NULL),
(1418, 20, 'rapports', 'attestation', 'Rapport Attestations de Paiement', 1, 6, '2026-06-29 23:25:51', NULL),
(1419, 20, 'rapports', 'paiements', 'Rapport Paiements', 1, 7, '2026-06-29 23:25:51', NULL),
(1420, 20, 'rapports', 'apurements', 'Rapport Apurements', 1, 8, '2026-06-29 23:25:51', NULL),
(1421, 20, 'rapports', 'quittances', 'Rapport Quittances', 1, 9, '2026-06-29 23:25:51', NULL),
(1422, 20, 'rapports', 'analytique', 'Rapports analytiques', 1, 10, '2026-06-29 23:25:51', NULL),
(1423, 20, 'rapports', 'export_pdf', 'Exporter PDF', 1, 11, '2026-06-29 23:25:51', NULL),
(1424, 20, 'rapports', 'export_excel', 'Exporter Excel', 1, 12, '2026-06-29 23:25:51', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `permissions_backup`
--

CREATE TABLE `permissions_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `role_id` int(11) NOT NULL,
  `module` varchar(100) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `autorise` tinyint(1) DEFAULT 1,
  `ordre` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `permissions_backup`
--

INSERT INTO `permissions_backup` (`id`, `role_id`, `module`, `action`, `description`, `autorise`, `ordre`, `created_at`, `updated_at`) VALUES
(1, 14, 'apurement', 'creer', NULL, 1, 0, '2026-06-12 14:46:43', NULL),
(2, 14, 'apurement', 'voir', NULL, 1, 0, '2026-06-12 14:47:16', NULL),
(3, 14, 'paiement', 'voir', NULL, 1, 0, '2026-06-12 14:56:16', NULL),
(4, 14, 'paiement', 'creer', NULL, 1, 0, '2026-06-12 14:56:29', NULL),
(5, 14, 'paiement', 'valider', NULL, 1, 0, '2026-06-12 14:56:39', NULL),
(6, 7, 'CORRECTIONS', 'ACCESS', NULL, 1, 0, '2026-06-15 16:49:23', NULL),
(7, 1, 'CORRECTIONS', 'ACCESS', NULL, 1, 0, '2026-06-15 16:49:23', NULL),
(9, 1, 'CORRECTIONS', 'ACCESS', NULL, 1, 0, '2026-06-15 17:06:53', NULL),
(10, 2, 'CORRECTIONS', 'ACCESS', NULL, 1, 0, '2026-06-15 17:06:53', NULL),
(11, 3, 'CORRECTIONS', 'ACCESS', NULL, 1, 0, '2026-06-15 17:06:53', NULL),
(12, 4, 'CORRECTIONS', 'ACCESS', NULL, 1, 0, '2026-06-15 17:06:53', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `provinces`
--

CREATE TABLE `provinces` (
  `id` int(11) NOT NULL,
  `nom` varchar(150) NOT NULL,
  `code_province` varchar(10) NOT NULL,
  `devise_principale` varchar(10) DEFAULT 'CDF',
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `provinces`
--

INSERT INTO `provinces` (`id`, `nom`, `code_province`, `devise_principale`, `actif`, `created_at`) VALUES
(1, 'BAS-UELE', 'BU', 'CDF', 1, '2026-05-31 19:31:06'),
(2, 'TSHOPO', 'TS', 'CDF', 1, '2026-05-31 19:31:06'),
(3, 'KINSHASA', 'KN', 'CDF', 1, '2026-05-31 19:31:06');

-- --------------------------------------------------------

--
-- Structure de la table `qr_verifications`
--

CREATE TABLE `qr_verifications` (
  `id` int(11) NOT NULL,
  `numero_document` varchar(120) DEFAULT NULL,
  `type_document` varchar(50) DEFAULT NULL,
  `resultat` enum('valide','invalide','annule','suspect') NOT NULL,
  `ip_inspecteur` varchar(100) DEFAULT NULL,
  `user_inspecteur_id` int(11) DEFAULT NULL,
  `date_verification` timestamp NOT NULL DEFAULT current_timestamp(),
  `pays` varchar(100) DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `appareil` varchar(255) DEFAULT NULL,
  `adresse_ip` varchar(100) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `quittances`
--

CREATE TABLE `quittances` (
  `id` int(11) NOT NULL,
  `numero_quittance` varchar(80) NOT NULL,
  `apurement_id` int(11) NOT NULL,
  `montant_acquitte` decimal(18,2) NOT NULL,
  `qr_hash` text DEFAULT NULL,
  `qr_data` text DEFAULT NULL,
  `date_emission` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_comptable_id` int(11) DEFAULT NULL,
  `penalite_assiette` decimal(18,2) DEFAULT 0.00,
  `penalite_recouvrement` decimal(18,2) DEFAULT 0.00,
  `nom_receptionniste` varchar(150) DEFAULT NULL,
  `fonction_receptionniste` varchar(150) DEFAULT NULL,
  `signature_receptionniste` varchar(255) DEFAULT NULL,
  `date_signature_receptionniste` datetime DEFAULT NULL,
  `date_signature_comptable` datetime DEFAULT NULL,
  `observation_signature` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `quittances`
--

INSERT INTO `quittances` (`id`, `numero_quittance`, `apurement_id`, `montant_acquitte`, `qr_hash`, `qr_data`, `date_emission`, `user_comptable_id`, `penalite_assiette`, `penalite_recouvrement`, `nom_receptionniste`, `fonction_receptionniste`, `signature_receptionniste`, `date_signature_receptionniste`, `date_signature_comptable`, `observation_signature`) VALUES
(1, 'QT-BU-CPR-26-000006', 2, 21258000.00, '04ad0644eae401343faeae942198c5c4', 'CP:dcfQt09rTbshsHoYDuSWl+u8M+1ozRGRXlnzdFvjy1vFKZ30zy4tooc5oU9HSzjk5wJycMJbrevmPCjvIHaKTA==', '2026-06-15 15:14:26', 3, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'QT-BU-CPR-26-000007', 10, 720000.00, '6a1b3156dc84bfe726e2b8ba9f7a1274', 'CP:jybSOEFR27/bRdYLEqKOp9hx/irrAH06ZmnNM94up1ovdnc9mJFfK0FrWQkiqZ3IgktfPyDSiK7xmtTlOqbT4A==', '2026-06-26 21:29:58', 3, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'QT-TS-KIS-26-000002', 12, 26700027.00, 'a6a3935b436f55e4938b34e5accb9f17', 'CP:yh/u8m87vEotY7L8tUXvK2Vbp4IcdaYoBGcl7DgXyk0C9IvM6UuHwYJFr61vj3NtRlADw+YOQh66c9Mc+FlOUQ==', '2026-06-27 18:26:25', 18, 0.00, 0.00, 'LOMBO LOFUMA Jean', 'COMPTABLE STE', NULL, '2026-06-27 20:26:25', '2026-06-27 20:26:25', 'DOSSIER VALIDE');

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nom_role` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `statut` enum('actif','inactif') DEFAULT 'actif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `nom_role`, `description`, `statut`, `created_at`, `updated_at`) VALUES
(1, 'SUPER_ADMIN', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(2, 'DG', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(3, 'DIRECTION_FINANCIERE', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(4, 'CHEF_CENTRE', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(5, 'CONSTATATEUR', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(6, 'LIQUIDATEUR', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(7, 'CONTROLEUR', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(8, 'ORDONNATEUR', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(9, 'RECOUVREMENT', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(10, 'DIRECTEUR RECOUVREMENT', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(11, 'CAISSIER', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(12, 'INSPECTEUR', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(13, 'AUDITEUR', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(14, 'APUREUR', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(15, 'COMPTABLE PUBLIC DES RECETTES', NULL, 'actif', '2026-06-20 17:55:40', NULL),
(16, 'Super Administrateur', 'Accès complet', 'actif', '2026-06-22 04:45:34', NULL),
(17, 'Administrateur Provincial', 'Gestion provinciale', 'actif', '2026-06-22 04:45:34', NULL),
(18, 'Chef Constatation', 'Supervision constatation', 'actif', '2026-06-22 04:45:34', NULL),
(19, 'Agent Constatation', 'Création des NT', 'actif', '2026-06-22 04:45:34', NULL),
(20, 'Receveur', 'Recouvrement et quittance', 'actif', '2026-06-22 04:45:34', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `secteurs_recettes`
--

CREATE TABLE `secteurs_recettes` (
  `id` int(11) NOT NULL,
  `province_id` int(11) NOT NULL,
  `nom_secteur` varchar(150) NOT NULL,
  `code_secteur` varchar(50) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `services_assiette`
--

CREATE TABLE `services_assiette` (
  `id` int(11) NOT NULL,
  `centre_id` int(11) NOT NULL,
  `nom_service` varchar(150) NOT NULL,
  `code_service` varchar(50) NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `direction_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `services_assiette`
--

INSERT INTO `services_assiette` (`id`, `centre_id`, `nom_service`, `code_service`, `actif`, `direction_id`) VALUES
(1, 3, 'INFRANSTRUCTURE ET TRAVAUX PUBLIQUE', 'ITPR', 1, NULL),
(2, 3, 'FINANCES', 'FIN', 1, 1),
(3, 3, 'COMMERCE', 'COMM', 1, NULL),
(4, 3, 'ENVIRONNEMENT', 'ENV', 1, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `signatures_numeriques`
--

CREATE TABLE `signatures_numeriques` (
  `id` int(11) NOT NULL,
  `document_type` enum('ND','NP','QT','PENALITE','AF','NPF') NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_signataire_id` int(11) NOT NULL,
  `hash_document` text NOT NULL,
  `signature_hash` text NOT NULL,
  `date_signature` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `taux_change_officiel`
--

CREATE TABLE `taux_change_officiel` (
  `id` int(11) NOT NULL,
  `devise` enum('USD') NOT NULL,
  `taux` decimal(18,4) NOT NULL,
  `date_application` date NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `user_direction_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `taux_change_officiel`
--

INSERT INTO `taux_change_officiel` (`id`, `devise`, `taux`, `date_application`, `actif`, `user_direction_id`, `created_at`) VALUES
(1, 'USD', 2800.0000, '2026-05-31', 0, NULL, '2026-05-31 19:38:03'),
(3, 'USD', 2800.0000, '2026-06-01', 1, 3, '2026-06-01 13:25:00');

-- --------------------------------------------------------

--
-- Structure de la table `taxations_offline_sync`
--

CREATE TABLE `taxations_offline_sync` (
  `id` int(11) NOT NULL,
  `local_id` varchar(100) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `contribuable_nom` varchar(200) DEFAULT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `plaque` varchar(100) DEFAULT NULL,
  `type_taxe` varchar(100) DEFAULT NULL,
  `article_id` int(11) DEFAULT NULL,
  `base_imposable` decimal(18,2) DEFAULT 0.00,
  `quantite` decimal(18,2) DEFAULT 1.00,
  `montant_cdf` decimal(18,2) DEFAULT 0.00,
  `gps_lat` varchar(100) DEFAULT NULL,
  `gps_lng` varchar(100) DEFAULT NULL,
  `photo` text DEFAULT NULL,
  `signature` text DEFAULT NULL,
  `statut` varchar(50) DEFAULT 'synchronise',
  `numero_nt` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `colonne_sync_nt` tinyint(1) DEFAULT 0,
  `note_taxation_id` int(11) DEFAULT NULL,
  `contribuable_id` int(11) DEFAULT NULL,
  `message_sync` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `taxations_offline_sync`
--

INSERT INTO `taxations_offline_sync` (`id`, `local_id`, `agent_id`, `contribuable_nom`, `telephone`, `plaque`, `type_taxe`, `article_id`, `base_imposable`, `quantite`, `montant_cdf`, `gps_lat`, `gps_lng`, `photo`, `signature`, `statut`, `numero_nt`, `created_at`, `colonne_sync_nt`, `note_taxation_id`, `contribuable_id`, `message_sync`) VALUES
(1, 'OFF-1781550792958-96328', NULL, 'LOMBO LOFUMA Jean', '+243820646942', 'CGO00045', 'chargement', 8, 0.00, 9864200.00, 198546000.00, NULL, NULL, NULL, NULL, 'synchronise', 'NT-OFF-26-000001', '2026-06-15 22:07:38', 1, 26, 19, 'NT créée automatiquement depuis PWA Offline. Photo/signature non stockées à cause de max_allowed_packet faible.'),
(2, 'OFF-1781551516300-46019', NULL, 'LOMBO LOFUMA JEAN', '+243820646942', 'CGO00934', 'autre', 8, 0.00, 1350000.00, 37805600.00, NULL, NULL, NULL, NULL, 'synchronise', 'NT-OFF-26-000002', '2026-06-15 22:07:39', 1, 27, 19, 'NT créée automatiquement depuis PWA Offline. Photo/signature non stockées à cause de max_allowed_packet faible.'),
(3, 'OFF-1781552591084-547', NULL, 'LOMBO LOFUMA JEAN', '+243820646942', 'CHK00023', 'chargement', 8, 0.00, 13504000.00, 378112000.00, NULL, NULL, NULL, NULL, 'synchronise', 'NT-OFF-26-000003', '2026-06-15 22:07:41', 1, 28, 19, 'NT créée automatiquement depuis PWA Offline. Photo/signature non stockées à cause de max_allowed_packet faible.'),
(4, 'OFF-1781553346032-98972', NULL, 'LOMBO LOFUMA JEAN', '+243820646942', 'CGOT00023', 'autre', 8, 0.00, 123000000.00, 3444000000.00, NULL, NULL, NULL, NULL, 'synchronise', 'NT-OFF-26-000004', '2026-06-15 22:07:43', 1, 29, 19, 'NT créée automatiquement depuis PWA Offline. Photo/signature non stockées à cause de max_allowed_packet faible.'),
(5, 'OFF-1781553577359-17886', NULL, 'FEZA BOSMIL', '+243820646942', 'RIRJB00023', 'autre', 8, 0.00, 13800.00, 386400.00, NULL, NULL, NULL, NULL, 'synchronise', 'NT-OFF-26-000005', '2026-06-15 22:07:45', 1, 30, 19, 'NT créée automatiquement depuis PWA Offline. Photo/signature non stockées à cause de max_allowed_packet faible.'),
(6, 'OFF-1781554055436-38400', NULL, 'LOMBO LOFUMA JEAN', '+243820646942', 'CDO00034', 'autre', 8, 0.00, 130000.00, 3640000.00, NULL, NULL, NULL, NULL, 'synchronise', 'NT-OFF-26-000006', '2026-06-15 22:07:46', 1, 31, 19, 'NT créée automatiquement depuis PWA Offline. Photo/signature non stockées à cause de max_allowed_packet faible.'),
(7, 'OFF-1781704928728-4030', NULL, 'LOMBO LOFUMA', '0820646942', 'CD08493', 'dechargement', 8, 0.00, 190000.00, 5320000.00, '-4.3613891', '15.1940353', NULL, NULL, 'synchronise', 'NT-OFF-26-000007', '2026-06-17 16:03:16', 1, 32, 1, 'NT créée automatiquement depuis PWA Offline');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nom` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `statut` enum('actif','inactif') DEFAULT 'actif',
  `derniere_connexion` datetime DEFAULT NULL,
  `password` text NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `centre_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `niveau` enum('centre','province','national') DEFAULT 'centre',
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `email`, `telephone`, `photo`, `statut`, `derniere_connexion`, `password`, `role_id`, `province_id`, `centre_id`, `service_id`, `niveau`, `actif`, `created_at`, `updated_at`) VALUES
(3, 'Jean LOMBO LOFUMA', 'admin@collectpay.cd', '+243820646942', NULL, 'actif', '2026-06-30 03:02:46', '$2y$10$10//7omG4BDJGaLciKfvt.nE70aIXjKwKubvHHmO6YfyvykV7OPm.', 1, 1, 1, NULL, 'national', 1, '2026-05-31 20:59:52', NULL),
(6, 'LOFUMA', 'inspecteur@collectpay.cd', 'INSPECTEUR', NULL, 'actif', '2026-06-29 19:13:19', '$2y$10$.99QXe/QD371Sw20xnuFv.oz70n/U5TT4DWbVMGGhdXkyCLgJ6Kvi', 12, 2, 3, NULL, 'national', 1, '2026-06-05 13:06:43', NULL),
(7, 'NKOSSA LOFUMA Thierry', 'od@collectpay.cd', '+243820646942', NULL, 'actif', '2026-06-29 18:30:42', '$2y$10$PaW/oPrzdG.LDOmlnxY4aO4H/p2LHPeXJGR.aeWvjxBOIrnJzqQV6', 8, 2, 3, NULL, 'province', 1, '2026-06-05 15:27:49', NULL),
(8, 'ZOE LOMBO', 'encodeur@collectpay.cd', '+243820646942', NULL, 'actif', '2026-06-27 16:21:44', '$2y$10$W/z5746je5FhF4BbXig7LOTebajj8JAxFTXKSZ7z5frzsHJzF96Su', 5, 2, 3, NULL, 'province', 1, '2026-06-05 21:10:14', '2026-06-22 08:18:12'),
(9, 'SETH LOMBO', 'liquidateur@collectpay.cd', '+243820646942', NULL, 'actif', '2026-06-29 18:29:29', '$2y$10$10oWRJOSRolAQE4imkMUvO8Xf8zpj.HBqaMIkXVczS45QvBS0ZHmO', 6, 2, 3, NULL, 'province', 1, '2026-06-05 21:11:26', NULL),
(10, 'rebecce LOMBO', 'controleur@collectpay.cd', '+243836551219', NULL, 'actif', '2026-06-29 18:30:03', '$2y$10$x/uS.0QOQsKSLeQZSeMH2uy6cyaEBY.cSN.wno3547./xu6VOYHIm', 7, 2, 3, NULL, 'province', 1, '2026-06-05 21:12:38', NULL),
(14, 'Florentine MAKAMBO', 'drecouc@collectpay.cd', '+243820646942', 'user_1782600906_6523.jpeg', 'actif', '2026-06-29 18:34:14', '$2y$10$RVJnCit0jKCPxl0uu1kP4OSkzAJjAjjTMYZ7KgyDQSIXUrOYzI6wK', 10, 2, 3, NULL, 'province', 1, '2026-06-05 21:23:14', '2026-06-28 00:55:06'),
(15, 'DADDY NGANDU', 'dg@collectpay.cd', '+243820064809', NULL, 'actif', '2026-06-29 19:05:17', '$2y$10$HL7GEAuniTKO0kV.mbneee5Eh/OY3nGKCLWwpuLwbKnBe6ZKTQexi', 2, 2, 3, NULL, 'province', 1, '2026-06-05 21:24:08', NULL),
(18, 'BOSMIL MENDE Josephe', 'comptable@collectpay.cd', '+243820646942', 'user_1782580316_7061.jpeg', 'actif', '2026-06-27 20:06:10', '$2y$10$lllYB9Jv5YJgt5zEiCgzbuLSuxchTpVPCBvJwqQYlRPgYnUQbYk16', 15, 2, 3, NULL, 'province', 1, '2026-06-12 15:01:53', '2026-06-27 19:12:31'),
(19, 'UTSHUDI LOFUMA Jean', 'apurement@collectpay.com', '+243820646942', 'user_1782524408_6688.jpeg', 'actif', '2026-06-27 19:07:14', '$2y$10$BrxsGEwVMvn7mIiSYCUzveg8HX4cQgVMqpVgrqj/lqC4e4BpslBJS', 14, 2, 3, 2, 'centre', 1, '2026-06-27 01:40:08', '2026-06-27 18:45:32'),
(20, 'Jean LOMBO LOFUMA', 'caisse@collectpay.cd', '+243820646942', 'user_1782576969_7266.jpeg', 'actif', '2026-06-27 18:18:14', '$2y$10$jj.DwDSLxlIYI4zqpTW.NeLun8IwGrGhorycSHOdRMAXxMv3rATf6', 11, 2, 3, NULL, 'centre', 1, '2026-06-27 16:16:09', NULL),
(21, 'NKOSSA LOFUMA Thierry', 'audit@collectpay.cd', '+243820646942', NULL, 'actif', '2026-06-29 19:10:22', '$2y$10$TUQWEP0/mVdlS6MjglRPWOytfRLchaBL0BS8jnHxzFiijY5ARd0rC', 13, 2, 3, NULL, 'centre', 1, '2026-06-29 17:10:03', NULL),
(22, 'ROOT IT', 'root@collectpay.cd', '+243820646942', 'user_1782753412_8854.jpeg', 'actif', '2026-06-29 19:17:09', '$2y$10$dEkK2BtFZ8SLcOVDDCaYsu.JxwqNep3GsjfQSMeQt3VWNDQxCoGri', 1, 2, 3, NULL, 'centre', 1, '2026-06-29 17:16:52', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `verification_logs`
--

CREATE TABLE `verification_logs` (
  `id` int(11) NOT NULL,
  `reference_verification` varchar(80) NOT NULL,
  `type_document` varchar(30) DEFAULT NULL,
  `numero_document` varchar(120) DEFAULT NULL,
  `resultat` enum('AUTHENTIQUE','NON_TROUVE') NOT NULL,
  `ip_address` varchar(80) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `verification_logs`
--

INSERT INTO `verification_logs` (`id`, `reference_verification`, `type_document`, `numero_document`, `resultat`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 'VERIFY-2026-782767796', 'NP', 'NP-BU-CPR-26-000012', 'AUTHENTIQUE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 21:16:36'),
(2, 'VERIFY-2026-782767878', 'NP', 'NP-BU-CPR-26-000012', 'AUTHENTIQUE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 21:17:58'),
(3, 'VERIFY-2026-782769408', 'NP', 'NP-BU-CPR-26-000012', 'AUTHENTIQUE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 21:43:28'),
(4, 'VERIFY-2026-782769412', 'NP', 'NP-BU-CPR-26-000012', 'AUTHENTIQUE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 21:43:32'),
(5, 'VERIFY-2026-782770369', 'NP', 'NP-BU-CPR-26-000012', 'AUTHENTIQUE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 21:59:29'),
(6, 'VERIFY-2026-782771641', 'NP', 'NP-BU-CPR-26-000012', 'AUTHENTIQUE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 22:20:41'),
(7, 'VERIFY-2026-782771681', 'NP', 'NP-BU-CPR-26-000012', 'AUTHENTIQUE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 22:21:21'),
(8, 'VERIFY-2026-782771885', 'NP', 'NP-BU-CPR-26-000012', 'AUTHENTIQUE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 22:24:45'),
(9, 'VERIFY-2026-782771932', 'NP', 'NP-BU-CPR-26-000012', 'AUTHENTIQUE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 22:25:32'),
(10, 'VERIFY-2026-782771958', 'NP', 'NP-BU-CPR-26-000012', 'AUTHENTIQUE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 22:25:58'),
(11, 'VERIFY-2026-782771969', 'NP', 'NP-BU-CPR-26-000012', 'AUTHENTIQUE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 22:26:09');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `actes_generateurs`
--
ALTER TABLE `actes_generateurs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `secteur_id` (`secteur_id`);

--
-- Index pour la table `alertes_systeme`
--
ALTER TABLE `alertes_systeme`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `amr`
--
ALTER TABLE `amr`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_amr` (`numero_amr`);

--
-- Index pour la table `apurements`
--
ALTER TABLE `apurements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_apurement_id` (`user_apurement_id`);

--
-- Index pour la table `articles_budgetaires`
--
ALTER TABLE `articles_budgetaires`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_code_acte_taux` (`code_article`,`nature_acte`(150),`acte_generateur`(150),`libelle_taux`),
  ADD KEY `fk_article_direction` (`direction_id`),
  ADD KEY `fk_article_service` (`service_id`);

--
-- Index pour la table `articles_budgetaires_new`
--
ALTER TABLE `articles_budgetaires_new`
  ADD PRIMARY KEY (`id`),
  ADD KEY `acte_generateur_id` (`acte_generateur_id`);

--
-- Index pour la table `article_taux_province`
--
ALTER TABLE `article_taux_province`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_article_province` (`article_id`,`province_id`),
  ADD KEY `province_id` (`province_id`);

--
-- Index pour la table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `avis_fractionnement`
--
ALTER TABLE `avis_fractionnement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_avis` (`numero_avis`),
  ADD UNIQUE KEY `uk_avis_np_unique` (`note_perception_id`),
  ADD KEY `user_recouvrement_id` (`user_recouvrement_id`);

--
-- Index pour la table `centres`
--
ALTER TABLE `centres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_centre` (`code_centre`),
  ADD KEY `province_id` (`province_id`);

--
-- Index pour la table `comptes_bancaires`
--
ALTER TABLE `comptes_bancaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `province_id` (`province_id`),
  ADD KEY `centre_id` (`centre_id`);

--
-- Index pour la table `contribuables`
--
ALTER TABLE `contribuables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_contribuable` (`code_contribuable`);

--
-- Index pour la table `contribuables_spontanes`
--
ALTER TABLE `contribuables_spontanes`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `contribuable_documents`
--
ALTER TABLE `contribuable_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contribuable_id` (`contribuable_id`);

--
-- Index pour la table `contribuable_qr`
--
ALTER TABLE `contribuable_qr`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contribuable_id` (`contribuable_id`);

--
-- Index pour la table `corrections_champs_bloques`
--
ALTER TABLE `corrections_champs_bloques`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `corrections_documents`
--
ALTER TABLE `corrections_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `numero_document` (`numero_document`),
  ADD KEY `reference_id` (`reference_id`);

--
-- Index pour la table `directions`
--
ALTER TABLE `directions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_direction` (`code_direction`);

--
-- Index pour la table `document_sequences`
--
ALTER TABLE `document_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sequence` (`type_document`,`province_id`,`centre_id`,`annee`),
  ADD KEY `province_id` (`province_id`),
  ADD KEY `centre_id` (`centre_id`);

--
-- Index pour la table `document_tokens`
--
ALTER TABLE `document_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

--
-- Index pour la table `historique_taux`
--
ALTER TABLE `historique_taux`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `impressions_documents`
--
ALTER TABLE `impressions_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doc_print` (`type_document`,`numero_document`);

--
-- Index pour la table `modes_paiement`
--
ALTER TABLE `modes_paiement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `notes_debit`
--
ALTER TABLE `notes_debit`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_nd` (`numero_nd`),
  ADD UNIQUE KEY `note_taxation_id` (`note_taxation_id`),
  ADD KEY `user_liquidateur_id` (`user_liquidateur_id`),
  ADD KEY `fk_nd_validateur` (`user_validateur_id`);

--
-- Index pour la table `notes_perception`
--
ALTER TABLE `notes_perception`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_np` (`numero_np`),
  ADD KEY `user_ordonnateur_id` (`user_ordonnateur_id`),
  ADD KEY `idx_np_type` (`type_np`),
  ADD KEY `idx_np_nd` (`note_debit_id`),
  ADD KEY `idx_note_debit_id` (`note_debit_id`);

--
-- Index pour la table `notes_perception_fractions`
--
ALTER TABLE `notes_perception_fractions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_fraction` (`numero_fraction`),
  ADD KEY `avis_id` (`avis_id`),
  ADD KEY `note_mere_id` (`note_mere_id`);

--
-- Index pour la table `notes_taxation`
--
ALTER TABLE `notes_taxation`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_nt` (`numero_nt`),
  ADD KEY `contribuable_id` (`contribuable_id`),
  ADD KEY `centre_id` (`centre_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `user_taxateur_id` (`user_taxateur_id`);

--
-- Index pour la table `notes_taxation_details`
--
ALTER TABLE `notes_taxation_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `note_taxation_id` (`note_taxation_id`),
  ADD KEY `article_id` (`article_id`);

--
-- Index pour la table `note_banques`
--
ALTER TABLE `note_banques`
  ADD PRIMARY KEY (`id`),
  ADD KEY `note_perception_id` (`note_perception_id`),
  ADD KEY `compte_bancaire_id` (`compte_bancaire_id`);

--
-- Index pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `note_perception_id` (`note_perception_id`),
  ADD KEY `fraction_id` (`fraction_id`),
  ADD KEY `mode_paiement_id` (`mode_paiement_id`),
  ADD KEY `user_comptable_id` (`user_comptable_id`);

--
-- Index pour la table `parametres_penalites_progressives`
--
ALTER TABLE `parametres_penalites_progressives`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `penalites_historique`
--
ALTER TABLE `penalites_historique`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_validation_id` (`user_validation_id`);

--
-- Index pour la table `periodes_taxation`
--
ALTER TABLE `periodes_taxation`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permissions_role_module_action` (`role_id`,`module`,`action`);

--
-- Index pour la table `provinces`
--
ALTER TABLE `provinces`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_province` (`code_province`);

--
-- Index pour la table `qr_verifications`
--
ALTER TABLE `qr_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_inspecteur_id` (`user_inspecteur_id`);

--
-- Index pour la table `quittances`
--
ALTER TABLE `quittances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_quittance` (`numero_quittance`),
  ADD KEY `apurement_id` (`apurement_id`),
  ADD KEY `user_comptable_id` (`user_comptable_id`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom_role` (`nom_role`);

--
-- Index pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `idx_role_permissions_role` (`role_id`),
  ADD KEY `idx_role_permissions_permission` (`permission_id`);

--
-- Index pour la table `secteurs_recettes`
--
ALTER TABLE `secteurs_recettes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `province_id` (`province_id`);

--
-- Index pour la table `services_assiette`
--
ALTER TABLE `services_assiette`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_service` (`code_service`),
  ADD KEY `centre_id` (`centre_id`),
  ADD KEY `fk_service_direction` (`direction_id`);

--
-- Index pour la table `signatures_numeriques`
--
ALTER TABLE `signatures_numeriques`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_signataire_id` (`user_signataire_id`);

--
-- Index pour la table `taux_change_officiel`
--
ALTER TABLE `taux_change_officiel`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_direction_id` (`user_direction_id`);

--
-- Index pour la table `taxations_offline_sync`
--
ALTER TABLE `taxations_offline_sync`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `province_id` (`province_id`),
  ADD KEY `centre_id` (`centre_id`);

--
-- Index pour la table `verification_logs`
--
ALTER TABLE `verification_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_verification` (`reference_verification`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `actes_generateurs`
--
ALTER TABLE `actes_generateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `alertes_systeme`
--
ALTER TABLE `alertes_systeme`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `amr`
--
ALTER TABLE `amr`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `apurements`
--
ALTER TABLE `apurements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `articles_budgetaires`
--
ALTER TABLE `articles_budgetaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `articles_budgetaires_new`
--
ALTER TABLE `articles_budgetaires_new`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `article_taux_province`
--
ALTER TABLE `article_taux_province`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT pour la table `avis_fractionnement`
--
ALTER TABLE `avis_fractionnement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `centres`
--
ALTER TABLE `centres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `comptes_bancaires`
--
ALTER TABLE `comptes_bancaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `contribuables`
--
ALTER TABLE `contribuables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `contribuables_spontanes`
--
ALTER TABLE `contribuables_spontanes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `contribuable_documents`
--
ALTER TABLE `contribuable_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `contribuable_qr`
--
ALTER TABLE `contribuable_qr`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `corrections_champs_bloques`
--
ALTER TABLE `corrections_champs_bloques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `corrections_documents`
--
ALTER TABLE `corrections_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `directions`
--
ALTER TABLE `directions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `document_sequences`
--
ALTER TABLE `document_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT pour la table `document_tokens`
--
ALTER TABLE `document_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT pour la table `historique_taux`
--
ALTER TABLE `historique_taux`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `impressions_documents`
--
ALTER TABLE `impressions_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT pour la table `modes_paiement`
--
ALTER TABLE `modes_paiement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `notes_debit`
--
ALTER TABLE `notes_debit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `notes_perception`
--
ALTER TABLE `notes_perception`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `notes_perception_fractions`
--
ALTER TABLE `notes_perception_fractions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notes_taxation`
--
ALTER TABLE `notes_taxation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT pour la table `notes_taxation_details`
--
ALTER TABLE `notes_taxation_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT pour la table `note_banques`
--
ALTER TABLE `note_banques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `parametres_penalites_progressives`
--
ALTER TABLE `parametres_penalites_progressives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `penalites_historique`
--
ALTER TABLE `penalites_historique`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `periodes_taxation`
--
ALTER TABLE `periodes_taxation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1425;

--
-- AUTO_INCREMENT pour la table `provinces`
--
ALTER TABLE `provinces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `qr_verifications`
--
ALTER TABLE `qr_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `quittances`
--
ALTER TABLE `quittances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `secteurs_recettes`
--
ALTER TABLE `secteurs_recettes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `services_assiette`
--
ALTER TABLE `services_assiette`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `signatures_numeriques`
--
ALTER TABLE `signatures_numeriques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `taux_change_officiel`
--
ALTER TABLE `taux_change_officiel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `taxations_offline_sync`
--
ALTER TABLE `taxations_offline_sync`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT pour la table `verification_logs`
--
ALTER TABLE `verification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `actes_generateurs`
--
ALTER TABLE `actes_generateurs`
  ADD CONSTRAINT `actes_generateurs_ibfk_1` FOREIGN KEY (`secteur_id`) REFERENCES `secteurs_recettes` (`id`);

--
-- Contraintes pour la table `apurements`
--
ALTER TABLE `apurements`
  ADD CONSTRAINT `apurements_ibfk_1` FOREIGN KEY (`user_apurement_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `articles_budgetaires`
--
ALTER TABLE `articles_budgetaires`
  ADD CONSTRAINT `fk_article_direction` FOREIGN KEY (`direction_id`) REFERENCES `directions` (`id`),
  ADD CONSTRAINT `fk_article_service` FOREIGN KEY (`service_id`) REFERENCES `services_assiette` (`id`);

--
-- Contraintes pour la table `articles_budgetaires_new`
--
ALTER TABLE `articles_budgetaires_new`
  ADD CONSTRAINT `articles_budgetaires_new_ibfk_1` FOREIGN KEY (`acte_generateur_id`) REFERENCES `actes_generateurs` (`id`);

--
-- Contraintes pour la table `article_taux_province`
--
ALTER TABLE `article_taux_province`
  ADD CONSTRAINT `article_taux_province_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles_budgetaires` (`id`),
  ADD CONSTRAINT `article_taux_province_ibfk_2` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`);

--
-- Contraintes pour la table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `avis_fractionnement`
--
ALTER TABLE `avis_fractionnement`
  ADD CONSTRAINT `avis_fractionnement_ibfk_1` FOREIGN KEY (`note_perception_id`) REFERENCES `notes_perception` (`id`),
  ADD CONSTRAINT `avis_fractionnement_ibfk_2` FOREIGN KEY (`user_recouvrement_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `centres`
--
ALTER TABLE `centres`
  ADD CONSTRAINT `centres_ibfk_1` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`);

--
-- Contraintes pour la table `comptes_bancaires`
--
ALTER TABLE `comptes_bancaires`
  ADD CONSTRAINT `comptes_bancaires_ibfk_1` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`),
  ADD CONSTRAINT `comptes_bancaires_ibfk_2` FOREIGN KEY (`centre_id`) REFERENCES `centres` (`id`);

--
-- Contraintes pour la table `contribuable_documents`
--
ALTER TABLE `contribuable_documents`
  ADD CONSTRAINT `contribuable_documents_ibfk_1` FOREIGN KEY (`contribuable_id`) REFERENCES `contribuables` (`id`);

--
-- Contraintes pour la table `contribuable_qr`
--
ALTER TABLE `contribuable_qr`
  ADD CONSTRAINT `contribuable_qr_ibfk_1` FOREIGN KEY (`contribuable_id`) REFERENCES `contribuables` (`id`);

--
-- Contraintes pour la table `document_sequences`
--
ALTER TABLE `document_sequences`
  ADD CONSTRAINT `document_sequences_ibfk_1` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`),
  ADD CONSTRAINT `document_sequences_ibfk_2` FOREIGN KEY (`centre_id`) REFERENCES `centres` (`id`);

--
-- Contraintes pour la table `notes_debit`
--
ALTER TABLE `notes_debit`
  ADD CONSTRAINT `fk_nd_validateur` FOREIGN KEY (`user_validateur_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `notes_debit_ibfk_1` FOREIGN KEY (`note_taxation_id`) REFERENCES `notes_taxation` (`id`),
  ADD CONSTRAINT `notes_debit_ibfk_2` FOREIGN KEY (`user_liquidateur_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `notes_perception`
--
ALTER TABLE `notes_perception`
  ADD CONSTRAINT `notes_perception_ibfk_1` FOREIGN KEY (`note_debit_id`) REFERENCES `notes_debit` (`id`),
  ADD CONSTRAINT `notes_perception_ibfk_2` FOREIGN KEY (`user_ordonnateur_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `notes_perception_fractions`
--
ALTER TABLE `notes_perception_fractions`
  ADD CONSTRAINT `notes_perception_fractions_ibfk_1` FOREIGN KEY (`avis_id`) REFERENCES `avis_fractionnement` (`id`),
  ADD CONSTRAINT `notes_perception_fractions_ibfk_2` FOREIGN KEY (`note_mere_id`) REFERENCES `notes_perception` (`id`);

--
-- Contraintes pour la table `notes_taxation`
--
ALTER TABLE `notes_taxation`
  ADD CONSTRAINT `notes_taxation_ibfk_1` FOREIGN KEY (`contribuable_id`) REFERENCES `contribuables` (`id`),
  ADD CONSTRAINT `notes_taxation_ibfk_2` FOREIGN KEY (`centre_id`) REFERENCES `centres` (`id`),
  ADD CONSTRAINT `notes_taxation_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services_assiette` (`id`),
  ADD CONSTRAINT `notes_taxation_ibfk_4` FOREIGN KEY (`user_taxateur_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `notes_taxation_details`
--
ALTER TABLE `notes_taxation_details`
  ADD CONSTRAINT `notes_taxation_details_ibfk_1` FOREIGN KEY (`note_taxation_id`) REFERENCES `notes_taxation` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notes_taxation_details_ibfk_2` FOREIGN KEY (`article_id`) REFERENCES `articles_budgetaires` (`id`);

--
-- Contraintes pour la table `note_banques`
--
ALTER TABLE `note_banques`
  ADD CONSTRAINT `note_banques_ibfk_1` FOREIGN KEY (`note_perception_id`) REFERENCES `notes_perception` (`id`),
  ADD CONSTRAINT `note_banques_ibfk_2` FOREIGN KEY (`compte_bancaire_id`) REFERENCES `comptes_bancaires` (`id`);

--
-- Contraintes pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD CONSTRAINT `paiements_ibfk_1` FOREIGN KEY (`note_perception_id`) REFERENCES `notes_perception` (`id`),
  ADD CONSTRAINT `paiements_ibfk_2` FOREIGN KEY (`fraction_id`) REFERENCES `notes_perception_fractions` (`id`),
  ADD CONSTRAINT `paiements_ibfk_3` FOREIGN KEY (`mode_paiement_id`) REFERENCES `modes_paiement` (`id`),
  ADD CONSTRAINT `paiements_ibfk_4` FOREIGN KEY (`user_comptable_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `penalites_historique`
--
ALTER TABLE `penalites_historique`
  ADD CONSTRAINT `penalites_historique_ibfk_1` FOREIGN KEY (`user_validation_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Contraintes pour la table `qr_verifications`
--
ALTER TABLE `qr_verifications`
  ADD CONSTRAINT `qr_verifications_ibfk_1` FOREIGN KEY (`user_inspecteur_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `quittances`
--
ALTER TABLE `quittances`
  ADD CONSTRAINT `quittances_ibfk_1` FOREIGN KEY (`apurement_id`) REFERENCES `apurements` (`id`),
  ADD CONSTRAINT `quittances_ibfk_2` FOREIGN KEY (`user_comptable_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `secteurs_recettes`
--
ALTER TABLE `secteurs_recettes`
  ADD CONSTRAINT `secteurs_recettes_ibfk_1` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`);

--
-- Contraintes pour la table `services_assiette`
--
ALTER TABLE `services_assiette`
  ADD CONSTRAINT `fk_service_direction` FOREIGN KEY (`direction_id`) REFERENCES `directions` (`id`),
  ADD CONSTRAINT `services_assiette_ibfk_1` FOREIGN KEY (`centre_id`) REFERENCES `centres` (`id`);

--
-- Contraintes pour la table `signatures_numeriques`
--
ALTER TABLE `signatures_numeriques`
  ADD CONSTRAINT `signatures_numeriques_ibfk_1` FOREIGN KEY (`user_signataire_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `taux_change_officiel`
--
ALTER TABLE `taux_change_officiel`
  ADD CONSTRAINT `taux_change_officiel_ibfk_1` FOREIGN KEY (`user_direction_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`),
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`centre_id`) REFERENCES `centres` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
