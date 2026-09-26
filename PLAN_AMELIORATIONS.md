# Plan d'amélioration de King and Qween

## Objectif du projet

King and Qween est une application web de gestion et de réservation pour un salon de coiffure. Elle permet :

- aux clients de consulter les prestations et promotions ;
- aux clients de réserver et gérer leurs rendez-vous ;
- aux coiffeurs de consulter leurs rendez-vous et plannings ;
- à l'administrateur de gérer les utilisateurs, services, coiffeurs, horaires, promotions et avis.

Le but de ce plan est de transformer le prototype actuel en application fiable, sécurisée et maintenable.

## Phase 0 - Clarifier la structure du projet

### 0.1 Choisir une seule copie du projet

**Problème :** le projet existe à la racine et dans le dossier `kingandqween/`.

**Actions :**

- comparer les deux copies ;
- identifier la version réellement utilisée ;
- conserver une seule version officielle ;
- archiver ou supprimer la copie inutile ;
- vérifier les chemins `BASE_URL` après cette décision.

**Validation :** toutes les pages utilisent les mêmes fichiers communs et les liens fonctionnent depuis la version conservée.

### 0.2 Documenter l'installation

Créer ou compléter un `README.md` avec :

- les prérequis PHP, MySQL et Composer ;
- l'installation des dépendances ;
- la création de la base de données ;
- la configuration locale ;
- le lancement du projet ;
- les comptes de test ;
- les commandes de vérification PHP.

## Phase 1 - Corriger les erreurs bloquantes

### 1.1 Synchroniser le formulaire de rendez-vous et la base

**Problème :** `frontend/rendezvous.php` utilise `adresse_domicile` et `telephone`, mais ces colonnes manquent dans la table `rendezvous`.

**Actions :**

- ajouter ces colonnes dans `database/schema.sql` ;
- les rendre nullables pour les rendez-vous au salon ;
- vérifier que les pages client, coiffeur et administrateur utilisent les mêmes noms ;
- tester une réservation au salon et une réservation à domicile.

### 1.2 Corriger la configuration

**Fichiers concernés :** `includes/config.php`, `includes/db.php`.

**Actions :**

- rendre `BASE_URL` configurable ;
- ne pas stocker les vrais mots de passe dans le code ;
- utiliser des variables d'environnement pour la base et SMTP ;
- remplacer la clé de session d'exemple ;
- désactiver `display_errors` en production ;
- adapter le cookie `secure` selon HTTPS ou environnement local.

**Validation :** l'application fonctionne en local et les erreurs détaillées ne sont pas exposées aux visiteurs.

## Phase 2 - Fiabiliser les réservations

### 2.1 Ajouter la durée des prestations

Ajouter `duree_minutes` dans `services`.

Cette donnée permettra de calculer l'heure de fin et de détecter les chevauchements.

### 2.2 Vérifier les disponibilités côté serveur

Avant de créer un rendez-vous, vérifier :

- que la date est valide et future ;
- que le coiffeur existe et possède le rôle `coiffeur` ;
- que le service existe ;
- que le service est proposé par ce coiffeur ;
- que le coiffeur travaille ce jour-là ;
- que le salon est ouvert ;
- qu'aucun rendez-vous confirmé ou en attente ne chevauche le créneau ;
- que la durée respecte les horaires de fermeture.

La validation JavaScript peut améliorer l'interface, mais la validation PHP reste obligatoire.

### 2.3 Empêcher les doubles réservations

Utiliser une transaction lors de la vérification et de l'insertion du rendez-vous. Recontrôler la disponibilité au moment de l'insertion pour éviter deux réservations simultanées.

### 2.4 Ajouter les absences et exceptions

Créer une table pour les congés, absences et fermetures exceptionnelles des coiffeurs.

## Phase 3 - Améliorer le modèle de données

### 3.1 Relier les coiffeurs aux services

Créer une table `coiffeur_services` contenant au minimum :

- `coiffeur_id` ;
- `service_id` ;
- éventuellement un prix personnalisé.

Le formulaire de réservation ne proposera ainsi que les prestations réellement disponibles.

### 3.2 Conserver les informations importantes du rendez-vous

Ajouter si nécessaire :

- `prix_final` ;
- `duree_minutes_snapshot` ;
- `adresse_domicile` ;
- `telephone` ;
- `updated_at` ;
- un motif d'annulation.

Conserver une copie du prix et de la durée au moment de la réservation évite que l'historique change lorsque le service est modifié plus tard.

### 3.3 Relier les avis aux rendez-vous

Ajouter `rendezvous_id` dans `avis` et autoriser un avis uniquement après un rendez-vous terminé. Cela limite les faux avis et les doublons.

### 3.4 Ajouter des contraintes et index

Ajouter :

- des index sur les clés étrangères et `date_heure` ;
- une contrainte d'unicité pour éviter plusieurs plannings identiques ;
- des contrôles sur les horaires de début et de fin ;
- des règles cohérentes pour les statuts.

## Phase 4 - Renforcer la sécurité

### 4.1 Ajouter une protection CSRF

Créer un jeton CSRF dans la session et l'exiger sur tous les formulaires qui modifient les données :

- inscription ;
- connexion ;
- réservation ;
- annulation ;
- gestion admin ;
- modération des avis.

### 4.2 Améliorer la validation des entrées

- utiliser `filter_input` ou des validations spécialisées ;
- convertir et vérifier les identifiants avec `filter_var` ;
- valider les dates et heures avec `DateTime` ;
- valider les numéros de téléphone ;
- limiter la longueur des textes ;
- conserver l'échappement HTML au moment de l'affichage.

### 4.3 Renforcer l'authentification

- utiliser `password_hash` et `password_verify` ;
- imposer une politique de mot de passe correcte ;
- ajouter une limitation des tentatives de connexion ;
- vérifier que les sessions sont régénérées après connexion ;
- contrôler les redirections après authentification.

### 4.4 Protéger les données sensibles

- ne jamais versionner les secrets SMTP ou base de données ;
- ajouter un fichier `.env.example` sans secrets réels ;
- ajouter `.env` au `.gitignore` ;
- supprimer les comptes et mots de passe de démonstration en production.

## Phase 5 - Réorganiser le code

### 5.1 Séparer progressivement les responsabilités

Organisation cible possible :

```text
config/
controllers/
models/
services/
views/
public/
 database/
tests/
```

La migration peut être progressive. Il faut commencer par la réservation, car c'est le parcours métier le plus important.

### 5.2 Créer des fonctions réutilisables

Centraliser :

- la vérification des rôles ;
- la validation des rendez-vous ;
- la recherche des disponibilités ;
- les changements de statut ;
- les messages flash ;
- l'envoi des emails.

### 5.3 Réduire les requêtes répétées

Créer des fonctions ou services communs pour les statistiques et les listes fréquemment utilisées. Ajouter des requêtes préparées partout où des valeurs utilisateur sont utilisées.

## Phase 6 - Améliorer l'expérience utilisateur

- afficher les créneaux réellement disponibles ;
- filtrer les services selon le coiffeur sélectionné ;
- afficher la durée et le prix estimé ;
- afficher une confirmation détaillée ;
- permettre l'annulation et la reprogrammation ;
- afficher des messages d'erreur compréhensibles ;
- rendre les champs à domicile obligatoires uniquement lorsque nécessaire ;
- vérifier l'affichage sur mobile.

## Phase 7 - Notifications et suivi

- envoyer un email après une demande de rendez-vous ;
- envoyer un email après confirmation, annulation ou reprogrammation ;
- envoyer un rappel avant le rendez-vous ;
- journaliser l'état d'envoi des emails ;
- prévoir une notification dans le tableau de bord.

## Phase 8 - Tests et qualité

### Tests fonctionnels minimum

- inscription avec email valide ;
- refus d'un email déjà utilisé ;
- connexion avec identifiants valides et invalides ;
- réservation au salon ;
- réservation à domicile ;
- refus d'une date passée ;
- refus d'un créneau déjà réservé ;
- annulation selon la politique ;
- accès interdit aux pages selon le rôle ;
- modération d'un avis ;
- envoi d'un email de notification.

### Vérifications techniques

- `php -l` sur tous les fichiers PHP ;
- tests SQL sur une base de test ;
- vérification des erreurs serveur ;
- contrôle des formulaires avec des valeurs inattendues ;
- test responsive sur mobile et ordinateur.

## Ordre recommandé d'exécution

1. Choisir la copie officielle du projet.
2. Corriger le schéma des rendez-vous.
3. Corriger la configuration et les secrets.
4. Ajouter la durée des prestations et les contrôles de disponibilité.
5. Empêcher les doubles réservations.
6. Ajouter la relation coiffeur-services.
7. Ajouter CSRF et renforcer la validation.
8. Ajouter les tests du parcours de réservation.
9. Ajouter les notifications et la reprogrammation.
10. Réorganiser progressivement le code.

## Résultat attendu

À la fin de ces étapes, l'application devra permettre une réservation fiable, cohérente avec les horaires réels, protégée contre les erreurs courantes et plus facile à faire évoluer par la suite.
