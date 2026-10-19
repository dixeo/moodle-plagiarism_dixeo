<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * French language strings for the Dixeo plagiarism plugin.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activity_not_enabled'] = 'Dixeo n\'est pas activé pour ce devoir.';
$string['analysis_aria_label'] = 'Analyse IA';
$string['analyze'] = 'Analyser';
$string['analyzing'] = 'Analyse en cours...';
$string['assign_trigger'] = 'Quand analyser les remises de devoir';
$string['assign_trigger_help'] = 'Analyser chaque brouillon déposé par l\'étudiant, ou attendre la remise pour notation. L\'analyse au dépôt donne un retour plus rapide mais consomme plus de crédits.';
$string['assign_trigger_submit'] = 'Uniquement à la remise finale';
$string['assign_trigger_upload'] = 'À chaque dépôt (brouillon)';
$string['assignment_feedback_enabled'] = 'Retour sur le devoir';
$string['assignment_feedback_enabled_help'] = 'Analyser les remises du devoir et afficher aux étudiants le texte d\'information. Chaque devoir conserve sa propre copie de ce réglage.';
$string['authorship_cancel'] = 'Annuler';
$string['authorship_confirm_and_submit'] = 'Confirmer et remettre';
$string['authorship_confirm_statement'] = 'Je confirme que j\'ai rédigé ce texte moi-même.';
$string['authorship_enabled'] = 'Activer la vérification d\'authenticité';
$string['authorship_enabled_desc'] = 'Lorsque cette option est activée, le texte de la remise peut être évalué pour un score de confiance d\'authenticité. Sous le seuil, un quiz de vérification peut être exigé.';
$string['authorship_error_missing_record'] = 'Les données de vérification sont manquantes. Veuillez annuler et réessayer.';
$string['authorship_quiz_load_error'] = 'Le contenu de vérification n\'a pas pu être chargé.';
$string['authorship_quiz_modal_title'] = 'Vérification requise';
$string['authorship_submit_quiz'] = 'Envoyer la vérification';
$string['authorship_threshold'] = 'Seuil de confiance';
$string['authorship_threshold_help'] = 'Score 0–100. Si la confiance initiale est inférieure à cette valeur, un quiz de vérification est exigé. Utilisez 0 pour toujours exiger un quiz, 100 pour ne jamais en exiger. Par défaut : 75.';
$string['authorship_verifying'] = 'Vérification...';
$string['automation_authorship_failed'] = 'La vérification d\'authenticité n\'a pas pu aboutir : {$a}';
$string['automation_authorship_incomplete'] = 'La vérification d\'authenticité n\'est pas terminée à temps (étape : {$a}).';
$string['automation_grade_failed'] = 'La note brouillon n\'a pas pu être préparée : {$a}';
$string['automation_grade_incomplete'] = 'La note brouillon n\'est pas terminée à temps.';
$string['automation_review_failed'] = 'L\'analyse de la remise n\'a pas pu aboutir : {$a}';
$string['automation_review_incomplete'] = 'L\'analyse de la remise n\'est pas terminée à temps.';
$string['close'] = 'Fermer';
$string['display_final_score'] = 'Score final : {$a} %';
$string['display_final_score_visibility_help'] = 'Afficher le score d\'authenticité final à l\'étudiant. Avec « Afficher le score et le commentaire », l\'explication de la combinaison du score initial et du test de vérification est également affichée. C\'est le seul score affiché lorsqu\'aucun test de vérification n\'a été généré.';
$string['display_initial_score'] = 'Score initial : {$a} %';
$string['display_initial_score_visibility_help'] = 'Afficher le score d\'authenticité initial, obtenu à partir du contenu remis. Avec « Afficher le score et le commentaire », l\'explication du score est également affichée. S\'applique uniquement lorsqu\'un test de vérification a été généré.';
$string['display_test_score'] = 'Score du test : {$a} %';
$string['display_test_score_visibility_help'] = 'Afficher la note du test de vérification à l\'étudiant. Avec « Afficher le score et le commentaire », l\'explication de la notation du test est également affichée. S\'applique uniquement lorsqu\'un test de vérification a été terminé.';
$string['dixeo:mod_assign_grade_submission'] = 'Noter un devoir avec l\'IA';
$string['dixeo:mod_assign_pre_submission'] = 'Obtenir un retour sur le devoir avant la remise';
$string['enable_auto_grading'] = 'Appliquer les notes automatiquement';
$string['enable_auto_grading_help'] = 'Lorsque cette option est activée, une note et un commentaire sont écrits automatiquement dans le carnet de notes dès qu\'un étudiant remet. Vous pouvez encore modifier la note ensuite. Lorsque cette option est désactivée, rien n\'est écrit tant que vous n\'utilisez pas le bouton Noter dans la vue d\'évaluation.';
$string['evaluation_mode'] = 'Mode d\'évaluation initiale';
$string['evaluation_mode_async'] = 'Asynchrone (tâche en file)';
$string['evaluation_mode_help'] = 'Synchrone : lancer l\'analyse à la remise (peut ralentir la page). Asynchrone : mettre le travail en file et le traiter en arrière-plan. Un échec synchrone est consigné et réessayé plus tard, sans bloquer la remise.';
$string['evaluation_mode_sync'] = 'Synchrone (dans l\'observateur)';
$string['event_analysis_requested'] = 'Remise envoyée pour analyse';
$string['event_authorship_checked'] = 'Évaluation d\'authenticité enregistrée';
$string['event_grade_draft_applied'] = 'Note brouillon appliquée';
$string['event_grade_draft_created'] = 'Note brouillon préparée';
$string['event_verification_started'] = 'Vérification d\'authenticité démarrée';
$string['event_verification_submitted'] = 'Vérification d\'authenticité envoyée';
$string['false'] = 'Faux';
$string['grade_draft_notice'] = 'Dixeo a prérempli une note et un commentaire ci-dessous. Rien n\'a été enregistré : relisez la suggestion, modifiez ce avec quoi vous n\'êtes pas d\'accord, puis enregistrez le formulaire vous-même.';
$string['grading_ai_disclaimer'] = 'Ce commentaire a été généré avec l\'IA.';
$string['grading_enabled'] = 'Notation';
$string['grading_enabled_help'] = 'Autoriser la notation assistée et les notes automatiques sur les devoirs. Chaque devoir conserve sa propre copie de ce réglage.';
$string['grading_not_enabled'] = 'La notation est désactivée pour ce devoir.';
$string['job_poll_timeout'] = 'L\'analyse prend trop de temps. Veuillez réessayer.';
$string['panel_maximize'] = 'Agrandir';
$string['panel_minimize'] = 'Réduire';
$string['pluginname'] = 'Dixeo';
$string['privacy:metadata:authorship'] = 'Stocke les contrôles de confiance d\'authenticité et les données optionnelles du quiz de vérification pour les remises de devoir.';
$string['privacy:metadata:authorship:area'] = 'Zone au sein du composant (par exemple submission).';
$string['privacy:metadata:authorship:component'] = 'Composant concerné (par exemple mod_assign).';
$string['privacy:metadata:authorship:final_confidence'] = 'Score de confiance d\'authenticité final après évaluation du quiz, le cas échéant.';
$string['privacy:metadata:authorship:final_explanation'] = 'Explication du score de confiance d\'authenticité final.';
$string['privacy:metadata:authorship:initial_confidence'] = 'Score de confiance d\'authenticité initial de l\'IA (0–100).';
$string['privacy:metadata:authorship:initial_explanation'] = 'Explication du score de confiance d\'authenticité initial.';
$string['privacy:metadata:authorship:itemid'] = 'Identifiant d\'élément dans le composant (par exemple identifiant de remise).';
$string['privacy:metadata:authorship:quiz_data'] = 'Questions du quiz de vérification générées, stockées en JSON.';
$string['privacy:metadata:authorship:quiz_explanation'] = 'Conclusion globale tirée des réponses au quiz de vérification.';
$string['privacy:metadata:authorship:quiz_grade'] = 'Note objective du quiz dérivée des bonnes réponses, le cas échéant.';
$string['privacy:metadata:authorship:quiz_question_feedbacks'] = 'Commentaires par question sur les réponses au quiz de vérification, stockés en JSON.';
$string['privacy:metadata:authorship:quiz_responses'] = 'Réponses de l\'étudiant au quiz de vérification, stockées en JSON.';
$string['privacy:metadata:authorship:response_timings'] = 'Heure de début du quiz de vérification et durée de chaque réponse.';
$string['privacy:metadata:authorship:status'] = 'Étape atteinte par l\'évaluation d\'authenticité.';
$string['privacy:metadata:authorship:timecreated'] = 'Date de création de l\'enregistrement d\'authenticité.';
$string['privacy:metadata:authorship:timemodified'] = 'Date de dernière mise à jour de l\'enregistrement d\'authenticité.';
$string['privacy:metadata:authorship:userid'] = 'L\'étudiant auquel l\'enregistrement d\'authenticité appartient.';
$string['privacy:metadata:dixeo'] = 'Le contenu de la remise est envoyé au service Dixeo pour produire un commentaire, des scores de confiance d\'authenticité et des notes proposées. Aucun résultat n\'est conservé par le service au-delà de la durée de la requête.';
$string['privacy:metadata:dixeo:activity_context'] = 'Le nom de l\'activité, les consignes et les critères de notation par rapport auxquels la remise est évaluée.';
$string['privacy:metadata:dixeo:quiz_responses'] = 'Les réponses de l\'étudiant au quiz de vérification d\'authenticité.';
$string['privacy:metadata:dixeo:submission_content'] = 'Le texte et les fichiers joints remis par l\'étudiant.';
$string['privacy:metadata:gradefeedback'] = 'Stocke les notes et commentaires proposés pour une remise, en attente de confirmation de l\'enseignant.';
$string['privacy:metadata:gradefeedback:analysis'] = 'Évaluation de la remise visible uniquement par les enseignants.';
$string['privacy:metadata:gradefeedback:area'] = 'Zone au sein du composant (par exemple submission).';
$string['privacy:metadata:gradefeedback:component'] = 'Composant concerné (par exemple mod_assign).';
$string['privacy:metadata:gradefeedback:criteria'] = 'Scores et remarques proposés pour le guide d\'évaluation ou la grille, stockés en JSON.';
$string['privacy:metadata:gradefeedback:feedback'] = 'Commentaire proposé pour l\'étudiant.';
$string['privacy:metadata:gradefeedback:grade'] = 'Note numérique proposée, pour la notation directe simple.';
$string['privacy:metadata:gradefeedback:gradingmethod'] = 'Méthode de notation pour laquelle la proposition a été produite.';
$string['privacy:metadata:gradefeedback:itemid'] = 'Identifiant d\'élément dans le composant (par exemple identifiant de remise).';
$string['privacy:metadata:gradefeedback:status'] = 'Indique si un enseignant a confirmé la proposition.';
$string['privacy:metadata:gradefeedback:timecreated'] = 'Date de création de la proposition.';
$string['privacy:metadata:gradefeedback:timemodified'] = 'Date de dernière mise à jour de la proposition.';
$string['privacy:metadata:gradefeedback:userid'] = 'L\'étudiant pour lequel la note est proposée.';
$string['privacy:metadata:presubmission'] = 'Stocke le commentaire reçu par un étudiant sur un brouillon avant la remise.';
$string['privacy:metadata:presubmission:area'] = 'Zone au sein du composant (par exemple submission).';
$string['privacy:metadata:presubmission:component'] = 'Composant concerné (par exemple mod_assign).';
$string['privacy:metadata:presubmission:feedback'] = 'Commentaire sur la remise brouillon.';
$string['privacy:metadata:presubmission:itemid'] = 'Identifiant d\'élément dans le composant (par exemple identifiant de remise).';
$string['privacy:metadata:presubmission:timecreated'] = 'Date de création du commentaire.';
$string['privacy:metadata:presubmission:timemodified'] = 'Date de dernière mise à jour du commentaire.';
$string['privacy:metadata:presubmission:userid'] = 'L\'étudiant auquel le commentaire appartient.';
$string['privacy:path:authorship'] = 'Vérification d\'authenticité';
$string['privacy:path:gradefeedback'] = 'Notes proposées';
$string['privacy:path:presubmission'] = 'Commentaire avant remise';
$string['question_locked'] = 'Question verrouillée';
$string['question_locked_tooltip'] = 'Cette question ne peut plus être modifiée';
$string['quiz_expired'] = 'Le temps est écoulé. Vos réponses jusqu\'ici ont été envoyées.';
$string['quiz_not_available'] = 'Ce quiz de vérification n\'est pas disponible.';
$string['quiz_payload_invalid'] = 'Le quiz de vérification a envoyé des données illisibles ({$a}). Rechargez la page et réessayez.';
$string['quiz_payload_too_large'] = 'Le quiz de vérification a envoyé trop de données ({$a}). Veuillez raccourcir votre réponse.';
$string['quiz_time_remaining'] = 'Temps restant : {$a}';
$string['settings_category_assignment'] = 'Devoir';
$string['settings_category_plagiarism_dixeo'] = 'Plagiat';
$string['settings_form_label'] = 'Ouvre les paramètres Dixeo de ce devoir.';
$string['settings_subcategory_authorship'] = 'Vérification d\'authenticité';
$string['settings_subcategory_feedback'] = 'Retour';
$string['settings_subcategory_grading'] = 'Notation';
$string['show_final_score'] = 'Score final';
$string['show_final_score_hide'] = 'Masquer à l\'étudiant';
$string['show_final_score_score'] = 'Afficher le score';
$string['show_final_score_scoreandfeedback'] = 'Afficher le score et le commentaire';
$string['show_initial_score'] = 'Score initial';
$string['show_initial_score_hide'] = 'Masquer à l\'étudiant';
$string['show_initial_score_score'] = 'Afficher le score';
$string['show_initial_score_scoreandfeedback'] = 'Afficher le score et le commentaire';
$string['show_test_score'] = 'Score du test';
$string['show_test_score_hide'] = 'Masquer à l\'étudiant';
$string['show_test_score_score'] = 'Afficher le score';
$string['show_test_score_scoreandfeedback'] = 'Afficher le score et le commentaire';
$string['status_failed_badge'] = 'Analyse échouée';
$string['status_pending_badge'] = 'Analyse...';
$string['student_disclosure'] = 'Information aux étudiants';
$string['student_disclosure_help'] = 'Texte affiché aux étudiants sur la page de remise. Expliquez que leur travail sera analysé et ce que cela implique. Laissez vide pour n\'afficher rien.';
$string['task_analyze_submission'] = 'Analyser une remise de devoir avec Dixeo';
$string['task_retry_pending_analysis'] = 'Relancer les analyses de remises Dixeo inachevées';
$string['task_stage_draft_grade'] = 'Préparer et appliquer une note Dixeo';
$string['timer_question_tooltip'] = 'La question suivante s\'affichera à la fin de ce minuteur.';
$string['timer_test_tooltip'] = 'Le test sera envoyé automatiquement à la fin de ce minuteur.';
$string['true'] = 'Vrai';
$string['verification_in_progress_badge'] = 'Vérification en cours';
$string['verification_next'] = 'Suivant';
$string['verification_previous'] = 'Précédent';
$string['verification_required_badge'] = 'Vérification requise';
$string['verification_test'] = 'Exiger un quiz de vérification';
$string['verification_test_help'] = 'Lorsque le score de confiance initial est sous le seuil, générer un court quiz sur la remise que l\'étudiant doit terminer. Désactivez cette option pour enregistrer le score sans jamais poser de questions.';
