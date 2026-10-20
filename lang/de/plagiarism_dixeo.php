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
 * German language strings for the Dixeo plagiarism plugin.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activity_not_enabled'] = 'Dixeo ist für diese Aufgabe nicht aktiviert.';
$string['analysis_aria_label'] = 'KI-Analyse';
$string['analyze'] = 'Analysieren';
$string['analyzing'] = 'Analyse läuft...';
$string['assign_trigger'] = 'Wann Aufgabenabgaben analysiert werden';
$string['assign_trigger_help'] = 'Jede hochgeladene Entwurfdatei analysieren oder bis zur Abgabe zur Bewertung warten. Analyse beim Hochladen gibt schnelleres Feedback, verbraucht aber mehr Credits.';
$string['assign_trigger_submit'] = 'Nur bei endgültiger Abgabe';
$string['assign_trigger_upload'] = 'Bei jedem Upload (Entwurf)';
$string['assignment_feedback_enabled'] = 'Aufgabenfeedback';
$string['assignment_feedback_enabled_help'] = 'Aufgabenabgaben analysieren und Studierenden den Hinweistext anzeigen. Jede Aufgabe behält eine eigene Kopie dieses Schalters.';
$string['authorship_confirm_statement'] = 'Ich bestätige, dass dieser Text von mir selbst verfasst wurde';
$string['authorship_enabled'] = 'Urheberschaftsprüfung aktivieren';
$string['authorship_enabled_desc'] = 'Wenn aktiviert, kann der Abgabetext auf Urheberschaftskonfidenz geprüft werden. Unter dem Schwellenwert kann ein Verifizierungsquiz erforderlich sein.';
$string['authorship_error_missing_record'] = 'Verifizierungsdaten fehlen. Bitte abbrechen und erneut versuchen.';
$string['authorship_quiz_load_error'] = 'Der Verifizierungsinhalt konnte nicht geladen werden.';
$string['authorship_quiz_modal_title'] = 'Verifizierung erforderlich';
$string['authorship_submit_quiz'] = 'Verifizierung absenden';
$string['authorship_threshold'] = 'Konfidenzschwelle';
$string['authorship_threshold_help'] = 'Wert 0–100. Liegt die Anfangskonfidenz darunter, ist ein Verifizierungsquiz erforderlich. 0 verlangt immer ein Quiz, 100 nie. Standard: 75.';
$string['authorship_verifying'] = 'Wird geprüft...';
$string['automation_authorship_failed'] = 'Die Urheberschaftsprüfung konnte nicht abgeschlossen werden: {$a}';
$string['automation_authorship_incomplete'] = 'Die Urheberschaftsprüfung wurde nicht rechtzeitig abgeschlossen (Schritt: {$a}).';
$string['automation_grade_failed'] = 'Die Entwurfsnote konnte nicht vorbereitet werden: {$a}';
$string['automation_grade_incomplete'] = 'Die Entwurfsnote wurde nicht rechtzeitig abgeschlossen.';
$string['automation_review_failed'] = 'Die Abgabeanalyse konnte nicht abgeschlossen werden: {$a}';
$string['automation_review_incomplete'] = 'Die Abgabeanalyse wurde nicht rechtzeitig abgeschlossen.';
$string['close'] = 'Schließen';
$string['display_final_score'] = 'Endergebnis: {$a} %';
$string['display_final_score_visibility_help'] = 'Das endgültige Urheberschaftsergebnis dem Studierenden anzeigen. Bei „Ergebnis und Feedback anzeigen“ wird auch erklärt, wie Anfangswert und Verifizierungstest kombiniert wurden. Dies ist das einzige angezeigte Ergebnis, wenn kein Verifizierungstest erzeugt wurde.';
$string['display_initial_score'] = 'Anfangsergebnis: {$a} %';
$string['display_initial_score_visibility_help'] = 'Das anfängliche Urheberschaftsergebnis aus dem Abgabeinhalt anzeigen. Bei „Ergebnis und Feedback anzeigen“ wird auch die Begründung angezeigt. Gilt nur, wenn ein Verifizierungstest erzeugt wurde.';
$string['display_test_score'] = 'Testergebnis: {$a} %';
$string['display_test_score_visibility_help'] = 'Die Note des Verifizierungstests dem Studierenden anzeigen. Bei „Ergebnis und Feedback anzeigen“ wird auch erklärt, wie der Test bewertet wurde. Gilt nur, wenn ein Verifizierungstest abgeschlossen wurde.';
$string['dixeo:mod_assign_grade_submission'] = 'Aufgabe mit KI bewerten';
$string['dixeo:mod_assign_pre_submission'] = 'Feedback zur Aufgabe vor der Abgabe erhalten';
$string['enable_auto_grading'] = 'Noten automatisch übernehmen';
$string['enable_auto_grading_help'] = 'Wenn aktiviert, werden Note und Feedback automatisch ins Notenbuch geschrieben, sobald ein Studierender abgibt. Die Note kann danach noch geändert werden. Wenn deaktiviert, wird nichts geschrieben, bis Sie in der Bewertungsansicht die Schaltfläche Bewerten nutzen.';
$string['evaluation_mode'] = 'Modus der Erstauswertung';
$string['evaluation_mode_async'] = 'Asynchron (Warteschlange)';
$string['evaluation_mode_help'] = 'Synchron: Analyse bei der Abgabe ausführen (kann die Seite verlangsamen). Asynchron: Arbeit einreihen und im Hintergrund verarbeiten. Ein synchroner Fehler wird protokolliert und später wiederholt, ohne die Abgabe zu blockieren.';
$string['evaluation_mode_sync'] = 'Synchron (im Beobachter)';
$string['event_analysis_requested'] = 'Abgabe zur Analyse gesendet';
$string['event_authorship_checked'] = 'Urheberschaftsbewertung gespeichert';
$string['event_grade_draft_applied'] = 'Entwurfsnote übernommen';
$string['event_grade_draft_created'] = 'Entwurfsnote vorbereitet';
$string['event_verification_started'] = 'Urheberschaftsverifizierung gestartet';
$string['event_verification_submitted'] = 'Urheberschaftsverifizierung abgesendet';
$string['false'] = 'Falsch';
$string['grade_draft_notice'] = 'Dixeo hat unten eine vorgeschlagene Note und Feedback eingesetzt. Es wurde nichts gespeichert: Prüfen Sie den Vorschlag, ändern Sie, womit Sie nicht einverstanden sind, und speichern Sie das Formular selbst.';
$string['grading_ai_disclaimer'] = 'Dieses Feedback wurde mit KI erzeugt.';
$string['grading_enabled'] = 'Bewertung';
$string['grading_enabled_help'] = 'Assistenzbewertung und automatische Noten für Aufgaben erlauben. Jede Aufgabe behält eine eigene Kopie dieses Schalters.';
$string['grading_not_enabled'] = 'Die Bewertung ist für diese Aufgabe ausgeschaltet.';
$string['job_poll_timeout'] = 'Die Analyse dauert zu lange. Bitte erneut versuchen.';
$string['panel_maximize'] = 'Maximieren';
$string['panel_minimize'] = 'Minimieren';
$string['pluginname'] = 'Dixeo';
$string['privacy:metadata:authorship'] = 'Speichert Urheberschaftskonfidenzprüfungen und optionale Verifizierungsquizdaten für Aufgabenabgaben.';
$string['privacy:metadata:authorship:area'] = 'Bereich innerhalb der Komponente (zum Beispiel submission).';
$string['privacy:metadata:authorship:component'] = 'Betroffene Komponente (zum Beispiel mod_assign).';
$string['privacy:metadata:authorship:final_confidence'] = 'Endgültiger Urheberschaftskonfidenzwert nach Quizauswertung, falls vorhanden.';
$string['privacy:metadata:authorship:final_explanation'] = 'Erläuterung des endgültigen Urheberschaftskonfidenzwerts.';
$string['privacy:metadata:authorship:initial_confidence'] = 'Anfänglicher KI-Urheberschaftskonfidenzwert (0–100).';
$string['privacy:metadata:authorship:initial_explanation'] = 'Erläuterung des anfänglichen Urheberschaftskonfidenzwerts.';
$string['privacy:metadata:authorship:itemid'] = 'Element-ID innerhalb der Komponente (zum Beispiel Abgabe-ID).';
$string['privacy:metadata:authorship:quiz_data'] = 'Erzeugte Verifizierungsquizfragen, gespeichert als JSON.';
$string['privacy:metadata:authorship:quiz_explanation'] = 'Gesamtschlussfolgerung aus den Antworten des Verifizierungsquiz.';
$string['privacy:metadata:authorship:quiz_grade'] = 'Objektive Quiznote aus richtigen Antworten, falls vorhanden.';
$string['privacy:metadata:authorship:quiz_question_feedbacks'] = 'Feedback je Frage zu den Verifizierungsquizantworten, gespeichert als JSON.';
$string['privacy:metadata:authorship:quiz_responses'] = 'Antworten der Studierenden auf das Verifizierungsquiz, gespeichert als JSON.';
$string['privacy:metadata:authorship:response_timings'] = 'Startzeit des Verifizierungsquiz und Dauer jeder Antwort.';
$string['privacy:metadata:authorship:status'] = 'Erreichter Stand der Urheberschaftsbewertung.';
$string['privacy:metadata:authorship:timecreated'] = 'Erstellungszeit des Urheberschaftsdatensatzes.';
$string['privacy:metadata:authorship:timemodified'] = 'Letzte Änderung des Urheberschaftsdatensatzes.';
$string['privacy:metadata:authorship:userid'] = 'Die Person, zu der der Urheberschaftsdatensatz gehört.';
$string['privacy:metadata:dixeo'] = 'Abgabeinhalte werden an den Dixeo-Dienst gesendet, um Feedback, Urheberschaftskonfidenzwerte und Notenvorschläge zu erzeugen. Der Dienst speichert keine Ergebnisse über die Dauer der Anfrage hinaus.';
$string['privacy:metadata:dixeo:activity_context'] = 'Aktivitätsname, Anweisungen und Bewertungskriterien, anhand derer die Abgabe beurteilt wird.';
$string['privacy:metadata:dixeo:quiz_responses'] = 'Die Antworten der Studierenden auf das Urheberschafts-Verifizierungsquiz.';
$string['privacy:metadata:dixeo:submission_content'] = 'Der Text und die angehängten Dateien der Abgabe.';
$string['privacy:metadata:gradefeedback'] = 'Speichert vorgeschlagene Noten und Feedback zu einer Abgabe, bis Lehrende sie bestätigen.';
$string['privacy:metadata:gradefeedback:analysis'] = 'Bewertung der Abgabe, die nur Lehrenden angezeigt wird.';
$string['privacy:metadata:gradefeedback:area'] = 'Bereich innerhalb der Komponente (zum Beispiel submission).';
$string['privacy:metadata:gradefeedback:component'] = 'Betroffene Komponente (zum Beispiel mod_assign).';
$string['privacy:metadata:gradefeedback:criteria'] = 'Vorgeschlagene Bewertungsleitfaden- oder Rubrikpunkte und Bemerkungen, gespeichert als JSON.';
$string['privacy:metadata:gradefeedback:feedback'] = 'Vorgeschlagenes Feedback für die Studierenden.';
$string['privacy:metadata:gradefeedback:grade'] = 'Vorgeschlagene numerische Note bei einfacher Direktbewertung.';
$string['privacy:metadata:gradefeedback:gradingmethod'] = 'Bewertungsmethode, für die der Vorschlag erzeugt wurde.';
$string['privacy:metadata:gradefeedback:itemid'] = 'Element-ID innerhalb der Komponente (zum Beispiel Abgabe-ID).';
$string['privacy:metadata:gradefeedback:status'] = 'Ob eine Lehrperson den Vorschlag bestätigt hat.';
$string['privacy:metadata:gradefeedback:timecreated'] = 'Erstellungszeit des Vorschlags.';
$string['privacy:metadata:gradefeedback:timemodified'] = 'Letzte Änderung des Vorschlags.';
$string['privacy:metadata:gradefeedback:userid'] = 'Die Person, für die die Note vorgeschlagen wird.';
$string['privacy:metadata:presubmission'] = 'Speichert das Feedback, das Studierende zu einem Entwurf vor der Abgabe erhalten haben.';
$string['privacy:metadata:presubmission:area'] = 'Bereich innerhalb der Komponente (zum Beispiel submission).';
$string['privacy:metadata:presubmission:component'] = 'Betroffene Komponente (zum Beispiel mod_assign).';
$string['privacy:metadata:presubmission:feedback'] = 'Feedback zum Entwurfsstand der Abgabe.';
$string['privacy:metadata:presubmission:itemid'] = 'Element-ID innerhalb der Komponente (zum Beispiel Abgabe-ID).';
$string['privacy:metadata:presubmission:timecreated'] = 'Erstellungszeit des Feedbacks.';
$string['privacy:metadata:presubmission:timemodified'] = 'Letzte Änderung des Feedbacks.';
$string['privacy:metadata:presubmission:userid'] = 'Die Person, zu der das Feedback gehört.';
$string['privacy:path:authorship'] = 'Urheberschaftsprüfung';
$string['privacy:path:gradefeedback'] = 'Vorgeschlagene Noten';
$string['privacy:path:presubmission'] = 'Feedback vor der Abgabe';
$string['question_locked'] = 'Frage gesperrt';
$string['question_locked_tooltip'] = 'Diese Frage kann nicht mehr bearbeitet werden';
$string['quiz_expired'] = 'Die Zeit ist abgelaufen. Ihre bisherigen Antworten wurden übermittelt.';
$string['quiz_not_available'] = 'Dieses Verifizierungsquiz ist nicht verfügbar.';
$string['quiz_payload_invalid'] = 'Das Verifizierungsquiz hat unlesbare Daten gesendet ({$a}). Bitte laden Sie die Seite neu und versuchen Sie es erneut.';
$string['quiz_payload_too_large'] = 'Das Verifizierungsquiz hat zu viele Daten gesendet ({$a}). Bitte kürzen Sie Ihre Antwort.';
$string['quiz_time_remaining'] = 'Verbleibende Zeit: {$a}';
$string['settings_category_assignment'] = 'Aufgabe';
$string['settings_category_plagiarism_dixeo'] = 'Plagiatsprüfung';
$string['settings_form_label'] = 'Öffnet die Dixeo-Einstellungen für diese Aufgabe.';
$string['settings_subcategory_authorship'] = 'Urheberschaftsprüfung';
$string['settings_subcategory_feedback'] = 'Feedback';
$string['settings_subcategory_grading'] = 'Bewertung';
$string['show_final_score'] = 'Endergebnis';
$string['show_final_score_hide'] = 'Vor Studierenden verbergen';
$string['show_final_score_score'] = 'Ergebnis anzeigen';
$string['show_final_score_scoreandfeedback'] = 'Ergebnis und Feedback anzeigen';
$string['show_initial_score'] = 'Anfangsergebnis';
$string['show_initial_score_hide'] = 'Vor Studierenden verbergen';
$string['show_initial_score_score'] = 'Ergebnis anzeigen';
$string['show_initial_score_scoreandfeedback'] = 'Ergebnis und Feedback anzeigen';
$string['show_test_score'] = 'Testergebnis';
$string['show_test_score_hide'] = 'Vor Studierenden verbergen';
$string['show_test_score_score'] = 'Ergebnis anzeigen';
$string['show_test_score_scoreandfeedback'] = 'Ergebnis und Feedback anzeigen';
$string['status_failed_badge'] = 'Analyse fehlgeschlagen';
$string['status_pending_badge'] = 'Analyse...';
$string['student_disclosure'] = 'Hinweis für Studierende';
$string['student_disclosure_help'] = 'Text, der Studierenden auf der Abgabeseite angezeigt wird. Erklären Sie, dass ihre Arbeit analysiert wird und was das bedeutet. Leer lassen, um nichts anzuzeigen.';
$string['task_analyze_submission'] = 'Eine Aufgabenabgabe mit Dixeo analysieren';
$string['task_retry_pending_analysis'] = 'Unvollständige Dixeo-Abgabeanalysen wiederholen';
$string['task_stage_draft_grade'] = 'Eine Dixeo-Note vorbereiten und übernehmen';
$string['timer_question_tooltip'] = 'Die nächste Frage wird angezeigt, wenn dieser Timer endet.';
$string['timer_test_tooltip'] = 'Der Test wird automatisch abgegeben, wenn dieser Timer endet.';
$string['true'] = 'Wahr';
$string['verification_in_progress_badge'] = 'Verifizierung läuft';
$string['verification_next'] = 'Weiter';
$string['verification_previous'] = 'Zurück';
$string['verification_required_badge'] = 'Verifizierung erforderlich';
$string['verification_submit'] = 'Absenden';
$string['verification_test'] = 'Verifizierungsquiz verlangen';
$string['verification_test_help'] = 'Liegt der anfängliche Konfidenzwert unter dem Schwellenwert, wird ein kurzes Quiz zur Abgabe erzeugt, das Studierende abschließen müssen. Deaktivieren Sie dies, um den Wert zu speichern, ohne Fragen zu stellen.';
