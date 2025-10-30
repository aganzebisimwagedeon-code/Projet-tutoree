<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/functions.php";

start_secure_session();

// Récupérer les politiques d'annulation depuis la base de données
$politiques = [];
$sql_politiques = "SELECT * FROM politiques_annulation ORDER BY type_acteur";
if ($result = $mysqli->query($sql_politiques)) {
    while ($row = $result->fetch_assoc()) {
        $politiques[$row['type_acteur']] = $row;
    }
    $result->free();
}

// Récupérer les horaires d'ouverture
$horaires = [];
$sql_horaires = "SELECT * FROM horaires_ouverture ORDER BY FIELD(jour_semaine, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche')";
if ($result = $mysqli->query($sql_horaires)) {
    while ($row = $result->fetch_assoc()) {
        $horaires[] = $row;
    }
    $result->free();
}

include __DIR__ . "/includes/header.php";
?>

<section class="page-title">
    <div class="container">
        <h1>Politiques d'Annulation et Conditions Générales</h1>
        <p>Informations importantes concernant les rendez-vous chez King & Queen</p>
    </div>
</section>

<section class="politique-content">
    <div class="container">
        <!-- Navigation rapide -->
        <div class="politique-nav">
            <h3>Navigation rapide</h3>
            <ul>
                <li><a href="#politique-client">Politique d'annulation - Clients</a></li>
                <li><a href="#politique-coiffeur">Politique d'annulation - Coiffeurs</a></li>
                <li><a href="#raisons-annulation">Raisons d'annulation par le coiffeur</a></li>
                <li><a href="#solutions-alternatives">Solutions et alternatives</a></li>
                <li><a href="#horaires-salon">Horaires du salon</a></li>
                <li><a href="#contact-urgence">Contact en cas d'urgence</a></li>
            </ul>
        </div>

        <!-- Introduction générale -->
        <div class="politique-section">
            <h2>Introduction</h2>
            <p>Chez King & Queen, nous nous engageons à offrir un service de qualité tout en maintenant une organisation optimale pour nos clients et nos coiffeurs. Cette page détaille nos politiques d'annulation, les raisons possibles d'annulation de rendez-vous et les solutions que nous proposons pour garantir votre satisfaction.</p>
            
            <div class="highlight-box">
                <h4>🎯 Notre engagement</h4>
                <p>Nous comprenons que les imprévus peuvent survenir. C'est pourquoi nous avons mis en place des politiques équitables qui protègent à la fois nos clients et nos professionnels, tout en maintenant la qualité de service que vous méritez.</p>
            </div>
        </div>

        <!-- Politique d'annulation côté client -->
        <div class="politique-section" id="politique-client">
            <h2>Politique d'Annulation - Côté Client</h2>
            
            <?php if (isset($politiques['client'])): ?>
                <div class="policy-card client-policy">
                    <h3>📋 Règles d'annulation pour les clients</h3>
                    
                    <div class="policy-details">
                        <div class="policy-item">
                            <h4>⏰ Délai minimum d'annulation</h4>
                            <p><strong><?php echo $politiques['client']['delai_minimum_heures']; ?> heures</strong> avant l'heure du rendez-vous</p>
                            <p class="explanation">Ce délai nous permet de proposer le créneau à d'autres clients et d'optimiser l'emploi du temps de nos coiffeurs.</p>
                        </div>

                        <?php if ($politiques['client']['penalite_active']): ?>
                            <div class="policy-item penalty-info">
                                <h4>💰 Politique de pénalité</h4>
                                <p><?php echo htmlspecialchars($politiques['client']['description_penalite']); ?></p>
                                <div class="penalty-details">
                                    <h5>Cas d'application de la pénalité :</h5>
                                    <ul>
                                        <li>Annulation moins de <?php echo $politiques['client']['delai_minimum_heures']; ?>h avant le rendez-vous</li>
                                        <li>Non-présentation sans préavis (no-show)</li>
                                        <li>Annulation répétée (plus de 2 fois dans le mois)</li>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="policy-item">
                            <h4>✅ Comment annuler votre rendez-vous</h4>
                            <div class="cancellation-methods">
                                <div class="method">
                                    <h5>1. Via votre espace client</h5>
                                    <p>Connectez-vous à votre compte et cliquez sur "Annuler" dans la liste de vos rendez-vous.</p>
                                </div>
                                <div class="method">
                                    <h5>2. Par téléphone</h5>
                                    <p>Appelez-nous au <strong>+33 1 23 45 67 89</strong> pendant nos heures d'ouverture.</p>
                                </div>
                                <div class="method">
                                    <h5>3. Par email</h5>
                                    <p>Envoyez un email à <strong>contact@kingandqween.com</strong> avec votre nom et la date du rendez-vous.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="exemptions-section">
                <h3>🚨 Cas d'exemption de pénalité</h3>
                <p>Nous comprenons que certaines situations sont imprévisibles. Les pénalités ne s'appliquent pas dans les cas suivants :</p>
                <div class="exemption-grid">
                    <div class="exemption-card">
                        <h4>🏥 Urgence médicale</h4>
                        <p>Hospitalisation, accident, maladie soudaine (justificatif médical requis)</p>
                    </div>
                    <div class="exemption-card">
                        <h4>👶 Urgence familiale</h4>
                        <p>Problème de garde d'enfant, urgence familiale grave</p>
                    </div>
                    <div class="exemption-card">
                        <h4>🚗 Force majeure</h4>
                        <p>Grève des transports, intempéries exceptionnelles, panne de véhicule</p>
                    </div>
                    <div class="exemption-card">
                        <h4>💼 Urgence professionnelle</h4>
                        <p>Convocation urgente, réunion imprévisible (justificatif employeur)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Politique d'annulation côté coiffeur -->
        <div class="politique-section" id="politique-coiffeur">
            <h2>Politique d'Annulation - Côté Coiffeur</h2>
            
            <?php if (isset($politiques['coiffeur'])): ?>
                <div class="policy-card coiffeur-policy">
                    <h3>💼 Règles d'annulation par nos coiffeurs</h3>
                    
                    <div class="policy-details">
                        <div class="policy-item">
                            <h4>⏰ Délai de notification</h4>
                            <p><strong><?php echo $politiques['coiffeur']['delai_minimum_heures']; ?> heures</strong> minimum avant le rendez-vous</p>
                            <p class="explanation">Nos coiffeurs s'engagent à vous prévenir le plus tôt possible en cas d'empêchement.</p>
                        </div>

                        <div class="policy-item">
                            <h4>🔄 Procédure de reprogrammation</h4>
                            <p><?php echo htmlspecialchars($politiques['coiffeur']['description_penalite']); ?></p>
                            <div class="reprogramming-steps">
                                <h5>Étapes de reprogrammation :</h5>
                                <ol>
                                    <li>Notification immédiate par téléphone et SMS</li>
                                    <li>Proposition de 3 créneaux alternatifs dans les 7 jours</li>
                                    <li>Priorité absolue sur les prochaines disponibilités</li>
                                    <li>Compensation possible selon la situation</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Raisons d'annulation par le coiffeur -->
        <div class="politique-section" id="raisons-annulation">
            <h2>Raisons d'Annulation par le Coiffeur</h2>
            <p>Bien que nous nous efforcions de maintenir tous nos rendez-vous, certaines situations peuvent contraindre nos coiffeurs à annuler :</p>

            <div class="reasons-grid">
                <div class="reason-category">
                    <h3>🏥 Raisons de santé</h3>
                    <div class="reason-list">
                        <div class="reason-item">
                            <h4>Maladie soudaine</h4>
                            <p>Grippe, gastro-entérite, ou toute maladie contagieuse pour protéger nos clients</p>
                        </div>
                        <div class="reason-item">
                            <h4>Accident ou blessure</h4>
                            <p>Incapacité temporaire à exercer (blessure aux mains, dos, etc.)</p>
                        </div>
                        <div class="reason-item">
                            <h4>Urgence médicale personnelle</h4>
                            <p>Hospitalisation, rendez-vous médical urgent non reportable</p>
                        </div>
                    </div>
                </div>

                <div class="reason-category">
                    <h3>👨‍👩‍👧‍👦 Raisons familiales</h3>
                    <div class="reason-list">
                        <div class="reason-item">
                            <h4>Urgence familiale</h4>
                            <p>Problème de santé d'un proche, urgence avec les enfants</p>
                        </div>
                        <div class="reason-item">
                            <h4>Garde d'enfant imprévisible</h4>
                            <p>Fermeture inattendue de crèche, maladie de la nounou</p>
                        </div>
                        <div class="reason-item">
                            <h4>Événement familial majeur</h4>
                            <p>Décès, naissance, mariage urgent dans la famille</p>
                        </div>
                    </div>
                </div>

                <div class="reason-category">
                    <h3>🏢 Raisons professionnelles</h3>
                    <div class="reason-list">
                        <div class="reason-item">
                            <h4>Double réservation</h4>
                            <p>Erreur de planning, deux clients pour le même créneau</p>
                        </div>
                        <div class="reason-item">
                            <h4>Formation obligatoire</h4>
                            <p>Formation de sécurité, mise à jour des certifications</p>
                        </div>
                        <div class="reason-item">
                            <h4>Problème technique</h4>
                            <p>Panne d'équipement essentiel, problème d'approvisionnement</p>
                        </div>
                    </div>
                </div>

                <div class="reason-category">
                    <h3>🕐 Raisons d'organisation</h3>
                    <div class="reason-list">
                        <div class="reason-item">
                            <h4>Pause déjeuner prolongée</h4>
                            <p>Rendez-vous précédent qui s'éternise, retard accumulé</p>
                        </div>
                        <div class="reason-item">
                            <h4>Congés d'urgence</h4>
                            <p>Congé maladie, congé pour événement familial</p>
                        </div>
                        <div class="reason-item">
                            <h4>Fermeture exceptionnelle</h4>
                            <p>Problème dans le salon, coupure d'électricité, dégât des eaux</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Solutions et alternatives -->
        <div class="politique-section" id="solutions-alternatives">
            <h2>Solutions et Alternatives Proposées</h2>
            <p>En cas d'annulation, nous mettons tout en œuvre pour minimiser les désagréments :</p>

            <div class="solutions-container">
                <div class="solution-category">
                    <h3>🔄 Reprogrammation prioritaire</h3>
                    <div class="solution-details">
                        <h4>Avantages de la reprogrammation :</h4>
                        <ul>
                            <li><strong>Priorité absolue</strong> sur les prochaines disponibilités</li>
                            <li><strong>Choix élargi</strong> de créneaux horaires</li>
                            <li><strong>Même coiffeur</strong> ou coiffeur de niveau équivalent</li>
                            <li><strong>Tarif préférentiel</strong> en cas d'annulation de notre fait</li>
                        </ul>
                        
                        <h4>Processus de reprogrammation :</h4>
                        <ol>
                            <li>Contact immédiat par téléphone</li>
                            <li>Proposition de 3 créneaux dans les 7 jours</li>
                            <li>Confirmation par SMS et email</li>
                            <li>Rappel 24h avant le nouveau rendez-vous</li>
                        </ol>
                    </div>
                </div>

                <div class="solution-category">
                    <h3>👥 Coiffeur de remplacement</h3>
                    <div class="solution-details">
                        <h4>Critères de sélection du remplaçant :</h4>
                        <ul>
                            <li><strong>Spécialisation similaire</strong> à votre coiffeur habituel</li>
                            <li><strong>Niveau d'expérience</strong> équivalent ou supérieur</li>
                            <li><strong>Disponibilité immédiate</strong> au créneau prévu</li>
                            <li><strong>Accès à votre historique</strong> de prestations</li>
                        </ul>
                        
                        <p><strong>Note :</strong> Si aucun remplaçant qualifié n'est disponible, nous privilégions la reprogrammation avec votre coiffeur habituel.</p>
                    </div>
                </div>

                <div class="solution-category">
                    <h3>🎁 Compensations et gestes commerciaux</h3>
                    <div class="compensation-grid">
                        <div class="compensation-item">
                            <h4>Annulation moins de 24h</h4>
                            <p><strong>Réduction de 10%</strong> sur la prochaine prestation</p>
                        </div>
                        <div class="compensation-item">
                            <h4>Annulation moins de 12h</h4>
                            <p><strong>Réduction de 15%</strong> + service express gratuit</p>
                        </div>
                        <div class="compensation-item">
                            <h4>Annulation moins de 6h</h4>
                            <p><strong>Réduction de 20%</strong> + prestation bonus</p>
                        </div>
                        <div class="compensation-item">
                            <h4>Annulation répétée</h4>
                            <p><strong>Séance gratuite</strong> après 3 annulations de notre fait</p>
                        </div>
                    </div>
                </div>

                <div class="solution-category">
                    <h3>🏠 Service à domicile d'urgence</h3>
                    <div class="solution-details">
                        <h4>Conditions d'éligibilité :</h4>
                        <ul>
                            <li>Annulation moins de 6 heures avant</li>
                            <li>Prestation initialement prévue au salon</li>
                            <li>Zone géographique couverte (Paris et proche banlieue)</li>
                            <li>Coiffeur disponible pour déplacement</li>
                        </ul>
                        
                        <p><strong>Supplément :</strong> Frais de déplacement offerts en cas d'annulation de notre fait, sinon 15€ de supplément.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Horaires du salon -->
        <div class="politique-section" id="horaires-salon">
            <h2>Horaires d'Ouverture du Salon</h2>
            <p>Nos horaires d'ouverture déterminent les créneaux disponibles pour vos rendez-vous :</p>

            <div class="horaires-container">
                <div class="horaires-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Jour</th>
                                <th>Horaires</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($horaires as $horaire): ?>
                                <tr class="<?php echo $horaire['ferme'] ? 'closed-day' : 'open-day'; ?>">
                                    <td><strong><?php echo $horaire['jour_semaine']; ?></strong></td>
                                    <td>
                                        <?php if ($horaire['ferme']): ?>
                                            <span class="closed-text">Fermé</span>
                                        <?php else: ?>
                                            <?php echo date('H:i', strtotime($horaire['heure_ouverture'])); ?> - 
                                            <?php echo date('H:i', strtotime($horaire['heure_fermeture'])); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $horaire['ferme'] ? 'status-closed' : 'status-open'; ?>">
                                            <?php echo $horaire['ferme'] ? 'Fermé' : 'Ouvert'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="horaires-info">
                    <h4>📋 Informations importantes :</h4>
                    <ul>
                        <li><strong>Dernier rendez-vous :</strong> 1 heure avant la fermeture</li>
                        <li><strong>Pause déjeuner :</strong> 12h30 - 13h30 (sauf samedi)</li>
                        <li><strong>Jours fériés :</strong> Fermé (sauf exceptions annoncées)</li>
                        <li><strong>Congés annuels :</strong> 2 semaines en août (dates communiquées en avance)</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Contact en cas d'urgence -->
        <div class="politique-section" id="contact-urgence">
            <h2>Contact en Cas d'Urgence</h2>
            <p>Pour toute situation urgente concernant votre rendez-vous :</p>

            <div class="contact-urgence-grid">
                <div class="contact-method priority-high">
                    <h3>📞 Téléphone (Priorité 1)</h3>
                    <div class="contact-details">
                        <p><strong>Numéro principal :</strong> +33 1 23 45 67 89</p>
                        <p><strong>Disponibilité :</strong> Pendant les heures d'ouverture</p>
                        <p><strong>Réponse :</strong> Immédiate</p>
                    </div>
                </div>

                <div class="contact-method priority-medium">
                    <h3>💬 SMS d'urgence</h3>
                    <div class="contact-details">
                        <p><strong>Numéro :</strong> +33 6 12 34 56 78</p>
                        <p><strong>Format :</strong> "URGENCE - [Votre nom] - [Date RDV] - [Motif]"</p>
                        <p><strong>Réponse :</strong> Dans l'heure</p>
                    </div>
                </div>

                <div class="contact-method priority-low">
                    <h3>📧 Email d'urgence</h3>
                    <div class="contact-details">
                        <p><strong>Adresse :</strong> urgence@kingandqween.com</p>
                        <p><strong>Objet :</strong> "URGENCE RDV - [Date] - [Votre nom]"</p>
                        <p><strong>Réponse :</strong> Dans les 2 heures</p>
                    </div>
                </div>
            </div>

            <div class="urgence-info">
                <h4>🚨 Que faire en cas d'urgence ?</h4>
                <div class="urgence-steps">
                    <div class="step">
                        <span class="step-number">1</span>
                        <div class="step-content">
                            <h5>Contactez-nous immédiatement</h5>
                            <p>Utilisez le téléphone en priorité, puis SMS si pas de réponse</p>
                        </div>
                    </div>
                    <div class="step">
                        <span class="step-number">2</span>
                        <div class="step-content">
                            <h5>Précisez la nature de l'urgence</h5>
                            <p>Maladie, accident, problème familial, transport, etc.</p>
                        </div>
                    </div>
                    <div class="step">
                        <span class="step-number">3</span>
                        <div class="step-content">
                            <h5>Proposez vos disponibilités</h5>
                            <p>Indiquez vos créneaux libres pour la reprogrammation</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Politique de confidentialité et données -->
        <div class="politique-section">
            <h2>Protection des Données et Confidentialité</h2>
            <p>Vos informations personnelles et l'historique de vos rendez-vous sont protégés selon le RGPD :</p>

            <div class="privacy-grid">
                <div class="privacy-item">
                    <h4>🔒 Données collectées</h4>
                    <ul>
                        <li>Nom, prénom, téléphone, email</li>
                        <li>Historique des rendez-vous et prestations</li>
                        <li>Préférences de coiffeur et horaires</li>
                        <li>Informations de paiement (cryptées)</li>
                    </ul>
                </div>
                <div class="privacy-item">
                    <h4>🎯 Utilisation des données</h4>
                    <ul>
                        <li>Gestion des rendez-vous et planning</li>
                        <li>Communication sur les annulations</li>
                        <li>Amélioration de nos services</li>
                        <li>Offres personnalisées (avec consentement)</li>
                    </ul>
                </div>
                <div class="privacy-item">
                    <h4>🛡️ Protection et droits</h4>
                    <ul>
                        <li>Données stockées de manière sécurisée</li>
                        <li>Accès limité au personnel autorisé</li>
                        <li>Droit d'accès, rectification, suppression</li>
                        <li>Conservation limitée à 3 ans après dernier RDV</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Réclamations et médiation -->
        <div class="politique-section">
            <h2>Réclamations et Résolution de Conflits</h2>
            <p>En cas de désaccord concernant une annulation ou nos politiques :</p>

            <div class="reclamation-process">
                <div class="process-step">
                    <h4>Étape 1 : Contact direct</h4>
                    <p>Contactez notre responsable clientèle au +33 1 23 45 67 89 ou clientele@kingandqween.com</p>
                    <span class="process-time">Délai de réponse : 24h</span>
                </div>
                <div class="process-arrow">→</div>
                <div class="process-step">
                    <h4>Étape 2 : Réclamation écrite</h4>
                    <p>Si pas de solution, envoyez une réclamation détaillée par courrier recommandé</p>
                    <span class="process-time">Délai de traitement : 15 jours</span>
                </div>
                <div class="process-arrow">→</div>
                <div class="process-step">
                    <h4>Étape 3 : Médiation</h4>
                    <p>Recours possible à un médiateur de la consommation agréé</p>
                    <span class="process-time">Gratuit et sans engagement</span>
                </div>
            </div>
        </div>

        <!-- Mise à jour des politiques -->
        <div class="politique-section">
            <h2>Évolution de nos Politiques</h2>
            <div class="update-info">
                <p><strong>Dernière mise à jour :</strong> <?php echo date('d/m/Y'); ?></p>
                <p>Nous nous réservons le droit de modifier ces politiques en cas de nécessité. Toute modification sera communiquée par email et sur notre site web au moins 30 jours avant son application.</p>
                
                <div class="notification-signup">
                    <h4>📧 Restez informé(e)</h4>
                    <p>Inscrivez-vous à notre newsletter pour être notifié(e) des changements de politiques et des nouveautés du salon.</p>
                    <a href="<?php echo BASE_URL; ?>frontend/contact.php" class="btn btn-primary">S'inscrire aux notifications</a>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Styles pour la page politique */
.politique-content {
    padding: 40px 0;
    background: #f8f9fa;
}

.politique-nav {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.politique-nav h3 {
    margin-bottom: 15px;
    color: #333;
}

.politique-nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.politique-nav li {
    margin-bottom: 8px;
}

.politique-nav a {
    color: #4361ee;
    text-decoration: none;
    padding: 5px 10px;
    border-radius: 5px;
    transition: background 0.3s;
}

.politique-nav a:hover {
    background: #f0f0f0;
}

.politique-section {
    background: white;
    margin-bottom: 30px;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.politique-section h2 {
    color: #333;
    border-bottom: 3px solid #4361ee;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.highlight-box {
    background: linear-gradient(135deg, #4361ee, #2575fc);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin: 20px 0;
}

.policy-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 25px;
    margin: 20px 0;
}

.client-policy {
    border-color: #28a745;
    background: #f8fff9;
}

.coiffeur-policy {
    border-color: #4361ee;
    background: #f8f9ff;
}

.policy-item {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e9ecef;
}

.policy-item:last-child {
    border-bottom: none;
}

.penalty-info {
    background: #fff3cd;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #ffc107;
}

.cancellation-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.method {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #4361ee;
}

.exemption-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.exemption-card {
    background: #e8f5e8;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #28a745;
}

.exemption-card h4 {
    color: #155724;
    margin-bottom: 10px;
}

.reasons-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

.reason-category {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #dee2e6;
}

.reason-category h3 {
    color: #495057;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #dee2e6;
}

.reason-item {
    background: white;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 8px;
    border-left: 4px solid #4361ee;
}

.reason-item h4 {
    color: #333;
    margin-bottom: 8px;
}

.solutions-container {
    margin-top: 20px;
}

.solution-category {
    background: #f8f9fa;
    padding: 25px;
    margin-bottom: 20px;
    border-radius: 10px;
    border: 1px solid #dee2e6;
}

.solution-category h3 {
    color: #4361ee;
    margin-bottom: 15px;
}

.compensation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.compensation-item {
    background: white;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #28a745;
    text-align: center;
}

.compensation-item h4 {
    color: #155724;
    margin-bottom: 10px;
}

.horaires-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-top: 20px;
}

.horaires-table table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.horaires-table th {
    background: #4361ee;
    color: white;
    padding: 15px;
    text-align: left;
}

.horaires-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
}

.open-day {
    background: #f8fff9;
}

.closed-day {
    background: #fff5f5;
}

.closed-text {
    color: #dc3545;
    font-weight: bold;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: 500;
}

.status-open {
    background: #d4edda;
    color: #155724;
}

.status-closed {
    background: #f8d7da;
    color: #721c24;
}

.horaires-info {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #dee2e6;
}

.contact-urgence-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.contact-method {
    padding: 20px;
    border-radius: 10px;
    border: 2px solid;
}

.priority-high {
    border-color: #dc3545;
    background: #fff5f5;
}

.priority-medium {
    border-color: #ffc107;
    background: #fffbf0;
}

.priority-low {
    border-color: #17a2b8;
    background: #f0f9ff;
}

.urgence-steps {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.step {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    flex: 1;
}

.step-number {
    background: #4361ee;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    flex-shrink: 0;
}

.privacy-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.privacy-item {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #dee2e6;
}

.reclamation-process {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.process-step {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #dee2e6;
    flex: 1;
    min-width: 250px;
}

.process-arrow {
    font-size: 24px;
    color: #4361ee;
    font-weight: bold;
}

.process-time {
    display: block;
    margin-top: 10px;
    color: #6c757d;
    font-style: italic;
}

.update-info {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #dee2e6;
}

.notification-signup {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-top: 15px;
    border: 1px solid #4361ee;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-primary {
    background: #4361ee;
    color: white;
}

.btn-primary:hover {
    background: #3651d4;
}

/* Responsive */
@media (max-width: 768px) {
    .horaires-container {
        grid-template-columns: 1fr;
    }
    
    .urgence-steps {
        flex-direction: column;
    }
    
    .reclamation-process {
        flex-direction: column;
    }
    
    .process-arrow {
        transform: rotate(90deg);
    }
    
    .exemption-grid,
    .reasons-grid,
    .contact-urgence-grid,
    .privacy-grid {
        grid-template-columns: 1fr;
    }
    
    .compensation-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}
</style>

<?php include __DIR__ . "/includes/footer.php"; ?>

