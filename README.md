# King and Qween – Salon de Coiffure (Gestion & Réservation)

Application web PHP/MySQL de gestion et de prise de rendez-vous pour le salon de coiffure **King and Qween**.

## Fonctionnalités principales

- **Espace Client :**
  - Consultation des prestations (tarifs, durées) et promotions en cours.
  - Prise de rendez-vous en ligne (au salon ou à domicile) avec filtrage dynamique des prestations par coiffeur et vérification en temps réel des créneaux disponibles.
  - Suivi des rendez-vous, annulation (selon la politique de délai) et reprogrammation.
  - Dépôt d'avis vérifiés (uniquement après un rendez-vous terminé).
  - Réception de notifications dans le tableau de bord et par email.
- **Espace Coiffeur :**
  - Tableau de bord d'activité et notifications.
  - Gestion des rendez-vous assignés (confirmation, annulation avec motif, finalisation).
  - Gestion du planning hebdomadaire et déclaration des congés / absences exceptionnelles.
  - Gestion du profil et des prestations proposées.
- **Espace Administrateur :**
  - Tableau de bord statistique global.
  - Gestion complète des utilisateurs, coiffeurs (avec photos et services associés), prestations (prix, durées), plannings, absences, promotions et horaires d'ouverture du salon.
  - Modération des avis clients et supervision des rendez-vous.

---

## Prérequis techniques

- **PHP** >= 8.0 (extensions requises : `mysqli`, `mbstring`, `gd` optionnelle pour le redimensionnement d'images, `openssl`)
- **MySQL** >= 5.7 ou **MariaDB** >= 10.3
- **Composer** (pour l'installation de `phpmailer/phpmailer`)

---

## Installation

### 1. Installer les dépendances PHP

```bash
composer install
```

### 2. Créer la base de données

Importez le fichier `database/schema.sql` dans votre serveur MySQL/MariaDB :

```bash
mysql -u root -p < database/schema.sql
```

Ce script crée la base `kingandqween`, l'ensemble des tables avec leurs contraintes et index, ainsi que les données initiales de démonstration.

### 3. Configuration locale (`.env`)

Copiez le fichier d'exemple `.env.example` vers `.env` à la racine du projet :

```bash
cp .env.example .env
```

Adaptez ensuite les paramètres dans `.env` selon votre environnement :

```ini
APP_ENV=development
APP_DEBUG=true
BASE_URL=http://localhost:8000/

DB_SERVER=127.0.0.1
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=
DB_NAME=kingandqween

MAIL_ENABLED=false
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your_email@example.com
MAIL_PASSWORD=your_smtp_password
```

> **Note :** Si `BASE_URL` n'est pas défini dans `.env`, l'application détecte automatiquement l'URL de base à partir de la requête HTTP courante.

### 4. Lancer le projet en local

Avec le serveur interne de PHP depuis la racine du projet :

```bash
php -S localhost:8000
```

Ouvrez ensuite `http://localhost:8000/` dans votre navigateur.

---

## Comptes de test (créés par `database/schema.sql`)

Tous les comptes de démonstration ci-dessous utilisent le mot de passe : **`Password123!`**

| Rôle | Email | Mot de passe | Espace dédié |
| :--- | :--- | :--- | :--- |
| **Administrateur** | `admin@kingandqween.com` | `Password123!` | `/backend/admin_dashboard.php` |
| **Coiffeur 1** | `jean.dupont@kingandqween.com` | `Password123!` | `/backend/coiffeur_dashboard.php` |
| **Coiffeur 2** | `marie.curie@kingandqween.com` | `Password123!` | `/backend/coiffeur_dashboard.php` |
| **Client** | `client@kingandqween.com` | `Password123!` | `/frontend/client_dashboard.php` |

---

## Commandes de vérification et de tests

### Vérification de la syntaxe PHP (`php -l`)

```bash
php tests/run_checks.php
```

Ce script vérifie :
1. La syntaxe PHP (`php -l`) de tous les fichiers du projet.
2. La cohérence du schéma SQL (`database/schema.sql`).
3. Les fonctions unitaires de sécurité (CSRF, validation d'entrées, politique de mot de passe, calcul de créneaux et détection de chevauchements).
