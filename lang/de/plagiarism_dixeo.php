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
$string['allow_backwards_navigation'] = 'Rückwärtsnavigation zulassen';
$string['allow_backwards_navigation_help'] = 'Studierende dürfen zu bereits bearbeiteten Fragen zurückkehren. Ist die Option aus, ist das Weitergehen endgültig und die Schaltfläche Weiter wird kurz deaktiviert, um Doppelklicks zu vermeiden.';
$string['analysis_aria_label'] = 'KI-Analyse';
$string['analyze'] = 'Analysieren';
$string['analyzing'] = 'Analyse läuft...';
$string['assign_trigger'] = 'Wann Aufgabenabgaben analysiert werden';
$string['assign_trigger_help'] = 'Jede hochgeladene Entwurfdatei analysieren oder bis zur Abgabe zur Bewertung warten. Analyse beim Hochladen gibt schnelleres Feedback, verbraucht aber mehr Credits.';
$string['assign_trigger_submit'] = 'Nur bei endgültiger Abgabe';
$string['assign_trigger_upload'] = 'Bei jedem Upload (Entwurf)';
$string['authorship_cancel'] = 'Abbrechen';
$string['authorship_confirm_and_submit'] = 'Bestätigen und abgeben';
$string['authorship_confirm_statement'] = 'Ich bestätige, dass dieser Text von mir selbst verfasst wurde';
$string['authorship_count_multiple_choice'] = 'Anzahl der Multiple-Choice-Fragen';
$string['authorship_count_multiple_choice_help'] = 'Wie viele Multiple-Choice-Fragen im Verifizierungsquiz erzeugt werden (0–5).';
$string['authorship_count_open'] = 'Anzahl der offenen Fragen';
$string['authorship_count_open_help'] = 'Wie viele offene Fragen (Freitext) im Verifizierungsquiz erzeugt werden (0–5).';
$string['authorship_count_true_false'] = 'Anzahl der Wahr/Falsch-Fragen';
$string['authorship_count_true_false_help'] = 'Wie viele Wahr/Falsch-Fragen im Verifizierungsquiz erzeugt werden (0–5).';
$string['authorship_enabled'] = 'Urheberschaftsprüfung aktivieren';
$string['authorship_enabled_desc'] = 'Wenn aktiviert, kann der Abgabetext auf Urheberschaftskonfidenz geprüft werden. Unter dem Schwellenwert kann ein Verifizierungsquiz erforderlich sein.';
$string['authorship_error_missing_record'] = 'Verifizierungsdaten fehlen. Bitte abbrechen und erneut versuchen.';
$string['authorship_quiz_load_error'] = 'Der Verifizierungsinhalt konnte nicht geladen werden.';
$string['authorship_quiz_modal_title'] = 'Verifizierung erforderlich';
$string['authorship_settings_heading'] = 'Urheberschaftsprüfung';
$string['authorship_settings_heading_desc'] = 'Festlegen, wann und wie die Urheberschaftsprüfung (Konfidenzwert und optionales Quiz) auf Abgaben angewendet wird.';
$string['authorship_submit_quiz'] = 'Verifizierung absenden';
$string['authorship_threshold'] = 'Konfidenzschwelle';
$string['authorship_threshold_help'] = 'Wert 0–100. Liegt die Anfangskonfidenz darunter, ist ein Verifizierungsquiz erforderlich. 0 verlangt immer ein Quiz, 100 nie. Standard: 75.';
$string['authorship_threshold_invalid'] = 'Geben Sie eine Konfidenzschwelle zwischen 0 und 100 ein.';
$string['authorship_verifying'] = 'Wird geprüft...';
$string['automation_authorship_failed'] = 'Die Urheberschaftsprüfung konnte nicht abgeschlossen werden: {$a}';
$string['automation_authorship_incomplete'] = 'Die Urheberschaftsprüfung wurde nicht rechtzeitig abgeschlossen (Schritt: {$a}).';
$string['automation_grade_failed'] = 'Die Entwurfsnote konnte nicht vorbereitet werden: {$a}';
$string['automation_grade_incomplete'] = 'Die Entwurfsnote wurde nicht rechtzeitig abgeschlossen.';
$string['automation_review_failed'] = 'Die Abgabeanalyse konnte nicht abgeschlossen werden: {$a}';
$string['automation_review_incomplete'] = 'Die Abgabeanalyse wurde nicht rechtzeitig abgeschlossen.';
$string['close'] = 'Schließen';
$string['display_final_score'] = 'Endergebnis: {$a} %';
$string['display_final_score_visibility'] = 'Endergebnis';
$string['display_final_score_visibility_help'] = 'Das endgültige Urheberschaftsergebnis dem Studierenden anzeigen. Bei „Ergebnis und Feedback anzeigen“ wird auch erklärt, wie Anfangswert und Verifizierungstest kombiniert wurden. Dies ist das einzige angezeigte Ergebnis, wenn kein Verifizierungstest erzeugt wurde.';
$string['display_initial_score'] = 'Anfangsergebnis: {$a} %';
$string['display_initial_score_visibility'] = 'Anfangsergebnis';
$string['display_initial_score_visibility_help'] = 'Das anfängliche Urheberschaftsergebnis aus dem Abgabeinhalt anzeigen. Bei „Ergebnis und Feedback anzeigen“ wird auch die Begründung angezeigt. Gilt nur, wenn ein Verifizierungstest erzeugt wurde.';
$string['display_test_score'] = 'Testergebnis: {$a} %';
$string['display_test_score_visibility'] = 'Testergebnis';
$string['display_test_score_visibility_help'] = 'Die Note des Verifizierungstests dem Studierenden anzeigen. Bei „Ergebnis und Feedback anzeigen“ wird auch erklärt, wie der Test bewertet wurde. Gilt nur, wenn ein Verifizierungstest abgeschlossen wurde.';
$string['dixeo:mod_assign_grade_submission'] = 'Aufgabe mit KI bewerten';
$string['dixeo:mod_assign_pre_submission'] = 'Feedback zur Aufgabe vor der Abgabe erhalten';
$string['enable_auto_grading'] = 'Noten automatisch übernehmen';
$string['enable_auto_grading_help'] = 'Wenn aktiviert, werden Note und Feedback automatisch ins Notenbuch geschrieben, sobald ein Studierender abgibt. Die Note kann danach noch geändert werden. Wenn deaktiviert, wird nichts geschrieben, bis Sie in der Bewertungsansicht die Schaltfläche Bewerten nutzen.';
$string['enable_mod_assign'] = 'Für Aufgaben aktivieren';
$string['enable_mod_assign_help'] = 'Dixeo für Aufgabenaktivitäten verfügbar machen. Jede Aufgabe muss dies weiterhin in ihren eigenen Einstellungen aktivieren.';
$string['enabled'] = 'Dixeo aktivieren';
$string['enabled_help'] = 'Hauptschalter des Plugins. Solange dieser aus ist, tut Dixeo auf der gesamten Website nichts, unabhängig von den Aktivitätseinstellungen.';
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
$string['job_poll_timeout'] = 'Die Analyse dauert zu lange. Bitte erneut versuchen.';
$string['mod_assign_grade_submission'] = 'Aufgabe mit KI bewerten';
$string['mod_assign_grade_submission_help'] = 'Ermöglicht KI-gestützte Bewertung beim Anzeigen einer Studierendenabgabe. Nur für Lehrende gedacht.';
$string['mod_assign_pre_submission'] = 'Feedback zur Aufgabe vor der Abgabe erhalten';
$string['mod_assign_pre_submission_help'] = 'Ermöglicht Feedback zur Abgabe, bevor sie zur Bewertung eingereicht wird (Schritt Analysieren).';
$string['next_button_delay_invalid'] = 'Geben Sie eine Verzögerung zwischen 0 und {$a} Sekunden ein.';
$string['next_button_delay_seconds'] = 'Verzögerung der Schaltfläche Weiter (Sekunden)';
$string['next_button_delay_seconds_help'] = 'Wie lange die Schaltfläche Weiter nach einem Klick deaktiviert bleibt, um Doppelklicks zu vermeiden. Gilt nur, wenn die Rückwärtsnavigation aus ist. Standard: 3.';
$string['panel_maximize'] = 'Maximieren';
$string['panel_minimize'] = 'Minimieren';
$string['plagiarism_settings_heading'] = 'Allgemein';
$string['plagiarism_settings_heading_desc'] = 'Steuern, wo Dixeo verfügbar ist und was Studierenden dazu mitgeteilt wird.';
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
$string['quiz_time_invalid'] = 'Geben Sie ein Zeitlimit von höchstens {$a} ein, oder 0 für kein Limit.';
$string['quiz_time_multiple_choice'] = 'Zeit pro Multiple-Choice-Frage';
$string['quiz_time_multiple_choice_help'] = 'Wie lange Studierende für jede Multiple-Choice-Frage haben. 0 bedeutet kein Limit. Standard: 1 Minute.';
$string['quiz_time_open'] = 'Zeit pro offener Frage';
$string['quiz_time_open_help'] = 'Wie lange Studierende für jede offene Frage haben. 0 bedeutet kein Limit. Standard: 3 Minuten.';
$string['quiz_time_remaining'] = 'Verbleibende Zeit: {$a}';
$string['quiz_time_true_false'] = 'Zeit pro Wahr/Falsch-Frage';
$string['quiz_time_true_false_help'] = 'Wie lange Studierende für jede Wahr/Falsch-Frage haben. 0 bedeutet kein Limit. Standard: 1 Minute.';
$string['quiz_total_time'] = 'Gesamtzeit des Quiz';
$string['quiz_total_time_help'] = 'Wie lange Studierende für das gesamte Verifizierungsquiz haben. Die Uhr läuft auf dem Server weiter, auch wenn die Seite geschlossen und erneut geöffnet wird. 0 bedeutet kein Limit. Standard: 3 Minuten.';
$string['setting_value_invalid'] = 'Wählen Sie eine der verfügbaren Optionen.';
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
$string['use_dixeo'] = 'Dixeo für diese Aktivität aktivieren';
$string['use_dixeo_help'] = 'Wenn aktiviert, werden Abgaben dieser Aufgabe von Dixeo analysiert. Studierende können vor der Abgabe Feedback zu einem Entwurf anfordern, und Abgaben werden auf Urheberschaft geprüft. Ist ein Verifizierungstest erforderlich, muss er abgeschlossen werden, bevor die Abgabe als vollständig gilt.';
$string['verification_in_progress_badge'] = 'Verifizierung läuft';
$string['verification_next'] = 'Weiter';
$string['verification_previous'] = 'Zurück';
$string['verification_quiz_timers_desc'] = 'Zeitlimits für das Verifizierungsquiz. Der Server führt die Uhr, das Schließen der Seite hält sie nicht an. Die Zeit je Frage wird erfasst und zur Auswertung gesendet.';
$string['verification_quiz_timers_heading'] = 'Zeit und Navigation des Verifizierungsquiz';
$string['verification_required_badge'] = 'Verifizierung erforderlich';
$string['verification_test'] = 'Verifizierungsquiz verlangen';
$string['verification_test_help'] = 'Liegt der anfängliche Konfidenzwert unter dem Schwellenwert, wird ein kurzes Quiz zur Abgabe erzeugt, das Studierende abschließen müssen. Deaktivieren Sie dies, um den Wert zu speichern, ohne Fragen zu stellen.';
$string['visibility_hide'] = 'Vor Studierenden verbergen';
$string['visibility_score'] = 'Ergebnis anzeigen';
$string['visibility_score_and_feedback'] = 'Ergebnis und Feedback anzeigen';
