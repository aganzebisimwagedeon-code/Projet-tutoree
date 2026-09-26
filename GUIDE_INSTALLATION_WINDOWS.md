# Guide d'installation et de démarrage sous Windows — King and Qween

Ce guide explique **pas à pas** comment installer, configurer et lancer l'application **King and Qween** sur un ordinateur équipé de **Windows (Windows 10 ou Windows 11)** avec **WampServer (WAMP)**, **XAMPP**, ou en **ligne de commande**.

---

## 1. Prérequis

Pour faire tourner le projet sous Windows, vous avez besoin d'un environnement comprenant **PHP (8.1 à 8.3)**, un serveur de base de données **MySQL / MariaDB** et un serveur web (**Apache** ou le serveur interne de PHP).

### Logiciels tout-en-un recommandés (au choix)
- **[WampServer (WAMP)](https://www.wampserver.com/)** ou **[wampserver.aviatechno.net](https://wampserver.aviatechno.net/)** *(dépôt officiel de téléchargement WampServer)* : inclut Apache, PHP, MySQL, MariaDB et phpMyAdmin.
  > **Important pour WampServer** : avant d'installer WampServer, assurez-vous d'avoir installé les **paquets Visual C++ Redistributable (VC++)** sur votre Windows (disponibles en un seul installeur *All-in-One* sur [wampserver.aviatechno.net](https://wampserver.aviatechno.net/)).
- **[XAMPP pour Windows](https://www.apachefriends.org/fr/index.html)** : inclut Apache, MariaDB, PHP et phpMyAdmin.

### Détail des composants requis
- **PHP** : version **8.1, 8.2 ou 8.3** avec les extensions suivantes activées :
  - `mysqli` (connexion à la base de données MySQL/MariaDB)
  - `gd` (redimensionnement des photos de la galerie et des coiffeurs)
  - `mbstring` et `openssl` (sécurité et envoi d'e-mails via PHPMailer)
- **Base de données** : **MySQL 8.0+** ou **MariaDB 10.4+**.
- **Navigateur web moderne** : Google Chrome, Microsoft Edge, Firefox ou Brave.
- *(Optionnel)* **[Git pour Windows](https://git-scm.com/download/win)** pour cloner le dépôt (sinon, téléchargez le projet en `.zip`).
- *(Optionnel)* **[Composer](https://getcomposer.org/download/)** : non obligatoire au démarrage car le dossier `vendor/` contenant **PHPMailer** est déjà inclus dans le projet.

---

## 2. Marche à suivre avec WampServer (WAMP)

### Étape 1 : Démarrer WampServer et vérifier l'icône verte
1. Installez **WampServer** dans le dossier par défaut (`C:\wamp64` sur Windows 64 bits, ou `C:\wamp` sur 32 bits).
2. Lancez **Wampserver64** depuis le bureau ou le menu Démarrer.
3. Regardez l'icône **W** dans la barre des tâches (en bas à droite de l'écran, près de l'horloge) :
   - Elle passe du **Rouge** $\rightarrow$ **Orange** $\rightarrow$ **Vert**.
   - Lorsqu'elle est **Verte**, Apache et MySQL/MariaDB fonctionnent correctement.
4. **Vérifier la version et les extensions PHP dans WampServer** :
   - Faites un **clic gauche** sur l'icône verte **W** $\rightarrow$ **PHP** $\rightarrow$ **Version** et vérifiez qu'une version **8.1, 8.2 ou 8.3** est sélectionnée.
   - Faites un **clic gauche** sur l'icône verte **W** $\rightarrow$ **PHP** $\rightarrow$ **Extensions PHP** et vérifiez que `mysqli`, `gd`, `mbstring` et `openssl` ont bien une coche verte.

---

### Étape 2 : Placer le projet dans le dossier `www` de WampServer
1. Ouvrez l'Explorateur Windows et allez dans le dossier :
   ```text
   C:\wamp64\www\
   ```
   *(ou faites un **clic gauche** sur l'icône verte WampServer $\rightarrow$ **Répertoire www**)*.
2. Placez-y le projet dans un sous-dossier nommé `kingandqween` :
   - **Avec Git** (ouvrez PowerShell ou Git Bash dans `C:\wamp64\www`) :
     ```powershell
     cd C:\wamp64\www
     git clone <URL_DU_DEPOT_GIT> kingandqween
     ```
   - **Sans Git (via fichier ZIP)** :
     Extrayez l'archive du projet dans `C:\wamp64\www\kingandqween` de sorte que le fichier `index.php` se trouve directement dans `C:\wamp64\www\kingandqween\index.php`.

---

### Étape 3 : Importer la base de données dans phpMyAdmin (WampServer)
1. Faites un **clic gauche** sur l'icône verte **W** $\rightarrow$ **phpMyAdmin** $\rightarrow$ **phpMyAdmin x.x.x** (ou ouvrez **[http://localhost/phpmyadmin](http://localhost/phpmyadmin)** dans votre navigateur).
2. Sur la page de connexion de phpMyAdmin :
   - **Utilisateur** : `root`
   - **Mot de passe** : *(laissez le champ totalement vide)*
   - **Choix du serveur** : sélectionnez **MySQL** *(par défaut sur le port `3306`)*.
   - Cliquez sur **Connexion** (ou *Exécuter*).
3. Cliquez sur l'onglet **Importer** (dans la barre du haut).
4. Cliquez sur **Choisir un fichier** (ou *Parcourir*) et sélectionnez le fichier :
   ```text
   C:\wamp64\www\kingandqween\database\schema.sql
   ```
5. Descendez tout en bas de la page et cliquez sur **Importer** (ou **Exécuter**).
6. La base de données **`kingandqween`**, ses **13 tables** et les comptes de démonstration sont maintenant créés !

> **Attention (Spécificité WampServer MySQL vs MariaDB) :**
> WampServer embarque souvent **à la fois** MySQL (port `3306`) et MariaDB (port `3307`).
> - Si vous avez choisi **MySQL** dans phpMyAdmin, gardez `DB_PORT=3306` dans `.env`.
> - Si vous avez choisi **MariaDB** dans phpMyAdmin, vérifiez son port (clic gauche sur l'icône W $\rightarrow$ MariaDB) et mettez ce port (`3306` ou `3307`) dans `DB_PORT` du fichier `.env`.

---

### Étape 4 : Configurer le fichier `.env`
1. Allez dans le dossier `C:\wamp64\www\kingandqween\`.
2. Si le fichier `.env` n'existe pas encore, dupliquez `.env.example` et renommez-le en `.env` :
   ```powershell
   cd C:\wamp64\www\kingandqween
   copy .env.example .env
   ```
3. Ouvrez `.env` avec un éditeur de texte (VS Code, Notepad++, Bloc-notes) et vérifiez les lignes suivantes :
   ```env
   APP_ENV=development
   APP_DEBUG=true

   # Laissez vide pour la détection automatique, ou indiquez l'URL locale :
   BASE_URL=http://localhost/kingandqween/

   # Connexion base de données WampServer par défaut :
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=kingandqween
   DB_USER=root
   DB_PASS=
   ```

---

### Étape 5 : Ouvrir le projet dans le navigateur (WampServer)
Vous avez deux façons d'ouvrir le site avec WampServer :

- **Accès direct par l'URL :**
  Ouvrez votre navigateur et allez sur :
  - **[http://localhost/kingandqween/](http://localhost/kingandqween/)**

- **Ou via un VirtualHost WampServer (Optionnel) :**
  1. Allez sur **[http://localhost/add_vhost.php](http://localhost/add_vhost.php)**.
  2. Nom du VirtualHost : `kingandqween.local`
  3. Chemin complet du dossier : `C:/wamp64/www/kingandqween`
  4. Cliquez sur **Démarrer la création du VirtualHost**, puis faites un **clic droit** sur l'icône verte WampServer $\rightarrow$ **Outils** $\rightarrow$ **Redémarrer DNS**.
  5. Si vous utilisez un VirtualHost, laissez `BASE_URL=` vide dans `.env` et accédez à `http://kingandqween.local/`.

---

## 3. Marche à suivre avec XAMPP (Alternative A)

1. Installez et ouvrez **XAMPP Control Panel**, puis cliquez sur **Start** en face de **Apache** et **MySQL** (les deux passent au vert).
2. Placez le dossier du projet dans :
   ```text
   C:\xampp\htdocs\kingandqween
   ```
3. Ouvrez **[http://localhost/phpmyadmin](http://localhost/phpmyadmin)**, cliquez sur l'onglet **Importer**, sélectionnez `C:\xampp\htdocs\kingandqween\database\schema.sql` et cliquez sur **Importer**.
4. Dans `C:\xampp\htdocs\kingandqween\.env`, vérifiez que `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_NAME=kingandqween`, `DB_USER=root` et `DB_PASS=`.
5. Ouvrez votre navigateur sur **[http://localhost/kingandqween/](http://localhost/kingandqween/)**.

---

## 4. Marche à suivre en ligne de commande PowerShell / VS Code (Alternative B)

Si vous souhaitez lancer le projet depuis **VS Code** ou **PowerShell** sur le port `8000` tout en utilisant la base de données de WampServer ou XAMPP :

1. Assurez-vous que **WampServer** (icône verte) ou **MySQL** dans XAMPP est démarré et que `database/schema.sql` est importé.
2. Dans le fichier `.env`, laissez `BASE_URL=` vide.
3. Ouvrez un terminal **PowerShell** dans le dossier du projet et lancez :
   ```powershell
   # Si vous utilisez le PHP de WampServer (adaptez le numéro de version PHP présent dans C:\wamp64\bin\php\) :
   C:\wamp64\bin\php\php8.2.0\php.exe -S localhost:8000

   # Ou si vous utilisez le PHP de XAMPP :
   C:\xampp\php\php.exe -S localhost:8000

   # Ou si PHP est déjà dans votre variable PATH Windows :
   php -S localhost:8000
   ```
4. Ouvrez votre navigateur à l'adresse :
   - **[http://localhost:8000/](http://localhost:8000/)**

---

## 5. Comptes de démonstration pour tester l'application

Une fois le fichier `database/schema.sql` importé, vous pouvez vous connecter sur la page **Connexion** avec les comptes suivants :

| Rôle | Adresse e-mail | Mot de passe | Espace accessible |
| :--- | :--- | :--- | :--- |
| **Administrateur** | `admin@kingandqween.com` | `password` | Tableau de bord Admin (utilisateurs, coiffeurs, services, plannings, RDV, avis, promotions, logs d'e-mails) |
| **Coiffeur 1** | `jean.dupont@kingandqween.com` | `password` | Espace Coiffeur (planning, absences, gestion des RDV, profil) |
| **Coiffeur 2** | `marie.curie@kingandqween.com` | `password` | Espace Coiffeur |
| **Client** | `client@kingandqween.com` | `password` | Espace Client (prise de RDV, historique, annulation, notifications) |

---

## 6. Configuration optionnelle : Envoi réel d'e-mails (Gmail SMTP)

Par défaut, **aucune configuration SMTP n'est obligatoire** pour tester le projet sous Windows :
- Lorsque vous demandez une réinitialisation de mot de passe ou qu'un rendez-vous change de statut, l'e-mail (avec le lien de réinitialisation) est enregistré dans la table `email_logs` (consultable depuis l'espace Administrateur) et dans le fichier `logs/mail.log`.

Si vous souhaitez envoyer de **vrais e-mails** via Gmail :
1. Activez la **validation en deux étapes** sur votre compte Google.
2. Créez un **Mot de passe d'application** (16 caractères) sur [https://myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords).
3. Remplissez la section `SMTP_*` dans le fichier `.env` :
   ```env
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_ENCRYPTION=tls
   SMTP_AUTH=true
   SMTP_USER=votre.adresse@gmail.com
   SMTP_PASS=xxxx xxxx xxxx xxxx
   SMTP_FROM_EMAIL=votre.adresse@gmail.com
   SMTP_FROM_NAME="King and Qween"
   ```

---

## 7. Dépannage des problèmes fréquents sous Windows (WAMP / XAMPP)

1. **L'icône de WampServer reste Orange ou Rouge**
   - Un autre programme utilise déjà le port `80` (IIS, Skype) ou le port `3306` (un autre service MySQL).
   - Faites un **clic droit** sur l'icône WampServer $\rightarrow$ **Outils** $\rightarrow$ **Tester le port 80** ou **Tester le port 3306** pour identifier le programme qui bloque, ou cliquez sur **Redémarrer les services**.

2. **Erreur `Unknown database 'kingandqween'` alors que vous l'avez importée dans WampServer**
   - Sur WampServer, vérifiez si vous avez importé la base dans **MySQL** (port `3306`) ou dans **MariaDB** (souvent port `3307`).
   - Adaptez `DB_PORT=3306` ou `DB_PORT=3307` dans votre fichier `.env` pour correspondre au serveur où se trouve la base.

3. **Erreur `Class "mysqli" not found` ou `Call to undefined function imagecreatetruecolor()`**
   - **Sur WampServer** : Clic gauche sur l'icône verte **W** $\rightarrow$ **PHP** $\rightarrow$ **Extensions PHP** $\rightarrow$ cliquez sur `mysqli` et `gd` pour les activer.
   - **Sur XAMPP** : Ouvrez `C:\xampp\php\php.ini`, décommentez (retirez le `;` devant) `extension=mysqli` et `extension=gd`, puis redémarrez Apache.

4. **La page s'affiche sans style CSS (liens cassés)**
   - Ouvrez le fichier `.env` et laissez `BASE_URL=` vide : l'application calculera automatiquement la bonne URL (`http://localhost/kingandqween/` ou `http://localhost:8000/`).
