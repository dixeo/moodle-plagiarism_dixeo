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
 * Italian language strings for the Dixeo plagiarism plugin.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activity_not_enabled'] = 'Questa funzione di Dixeo non è abilitata per questo compito.';
$string['analysis_aria_label'] = 'Analisi IA';
$string['analyze'] = 'Analizza';
$string['analyzing'] = 'Analisi in corso...';
$string['assign_trigger'] = 'Quando analizzare le consegne del compito';
$string['assign_trigger_help'] = 'Analizza ogni bozza caricata dallo studente oppure attendi che consegni per la valutazione. Analizzare al caricamento fornisce un feedback più rapido, ma consuma più crediti.';
$string['assign_trigger_submit'] = 'Solo alla consegna finale';
$string['assign_trigger_upload'] = 'A ogni caricamento (bozza)';
$string['assignment_feedback_enabled'] = 'Feedback sul compito';
$string['assignment_feedback_enabled_help'] = 'Fornisci agli studenti un feedback di supporto sul loro lavoro prima della consegna e analizza automaticamente le consegne. Ogni compito conserva la propria copia di questo interruttore.';
$string['authorship_confirm_statement'] = 'Confermo di aver scritto questo testo da solo';
$string['authorship_enabled'] = 'Attiva la verifica di paternità';
$string['authorship_enabled_desc'] = 'Se attivato, il testo della consegna può essere valutato con un punteggio di fiducia di paternità. Sotto la soglia, può essere richiesto un quiz di verifica.';
$string['authorship_error_missing_record'] = 'Mancano i dati di verifica. Annulla e riprova.';
$string['authorship_quiz_load_error'] = 'Impossibile caricare il contenuto di verifica.';
$string['authorship_quiz_modal_title'] = 'Verifica richiesta';
$string['authorship_submit_quiz'] = 'Invia verifica';
$string['authorship_threshold'] = 'Soglia di fiducia';
$string['authorship_threshold_help'] = 'Punteggio 0–100. Un quiz di verifica è richiesto quando la fiducia iniziale è inferiore a questo valore. 0 non lo richiede mai; 100 lo richiede a meno che il punteggio sia 100. Predefinito: 75.';
$string['authorship_verifying'] = 'Verifica in corso...';
$string['automation_authorship_failed'] = 'Impossibile completare il controllo di paternità: {$a}';
$string['automation_authorship_incomplete'] = 'Il controllo di paternità non è stato completato in tempo (fase: {$a}).';
$string['automation_grade_failed'] = 'Impossibile preparare il voto in bozza: {$a}';
$string['automation_grade_incomplete'] = 'Il voto in bozza non è stato completato in tempo.';
$string['automation_review_failed'] = 'Impossibile completare l\'analisi della consegna: {$a}';
$string['automation_review_incomplete'] = 'L\'analisi della consegna non è stata completata in tempo.';
$string['close'] = 'Chiudi';
$string['display_final_score'] = 'Punteggio finale: {$a} %';
$string['display_final_score_visibility_help'] = 'Mostra allo studente il punteggio finale di paternità. Con «Mostra punteggio e commento» viene anche mostrato come il punteggio iniziale e il test di verifica sono stati combinati. È l\'unico punteggio mostrato quando non è stato generato un test di verifica.';
$string['display_initial_score'] = 'Punteggio iniziale: {$a} %';
$string['display_initial_score_visibility_help'] = 'Mostra il punteggio iniziale di paternità, ottenuto dalla lettura del contenuto consegnato. Con «Mostra punteggio e commento» viene anche mostrata la spiegazione. Si applica solo quando è stato generato un test di verifica.';
$string['display_test_score'] = 'Punteggio del test: {$a} %';
$string['display_test_score_visibility_help'] = 'Mostra allo studente il voto del test di verifica. Con «Mostra punteggio e commento» viene anche mostrato come il test è stato valutato. Si applica solo quando è stato completato un test di verifica.';
$string['dixeo:mod_assign_grade_submission'] = 'Valutare il compito con l\'IA';
$string['dixeo:mod_assign_pre_submission'] = 'Ricevere un feedback sul compito prima della consegna';
$string['enable_auto_grading'] = 'Applica i voti automaticamente';
$string['enable_auto_grading_help'] = 'Se attivato, Dixeo valuta ogni consegna in background poco dopo che lo studente l\'ha consegnata e registra il voto a nome del docente che ha attivato questa opzione. Puoi modificare il voto in seguito. Se quel docente non può più valutare questo compito, la proposta viene conservata come bozza che un docente potrà verificare. Se disattivato, non viene valutato nulla finché non usi il pulsante Valuta nella vista di valutazione. Richiede Valutazione.';
$string['event_analysis_requested'] = 'Consegna inviata per l\'analisi';
$string['event_authorship_checked'] = 'Valutazione di paternità registrata';
$string['event_grade_draft_applied'] = 'Voto in bozza applicato';
$string['event_grade_draft_created'] = 'Voto in bozza preparato';
$string['event_verification_started'] = 'Verifica di paternità avviata';
$string['event_verification_submitted'] = 'Verifica di paternità inviata';
$string['false'] = 'Falso';
$string['grade_draft_notice'] = 'Dixeo ha compilato sotto un voto e un commento suggeriti. Non è stato salvato nulla: rivedi il suggerimento, modifica ciò che non ti convince e salva tu stesso il modulo.';
$string['grading_ai_disclaimer'] = 'Questo commento è stato generato con l\'IA.';
$string['grading_enabled'] = 'Valutazione';
$string['grading_enabled_help'] = 'Consenti la valutazione assistita e i voti automatici sui compiti. Ogni compito conserva la propria copia di questo interruttore.';
$string['grading_not_enabled'] = 'La valutazione è disattivata per questo compito.';
$string['job_poll_timeout'] = 'L\'analisi sta impiegando troppo tempo. Riprova.';
$string['panel_maximize'] = 'Ingrandisci';
$string['panel_minimize'] = 'Riduci';
$string['pluginname'] = 'Dixeo';
$string['privacy:metadata:authorship'] = 'Memorizza i controlli di fiducia di paternità e i dati opzionali del quiz di verifica delle consegne dei compiti.';
$string['privacy:metadata:authorship:area'] = 'Area all\'interno del componente (ad esempio submission).';
$string['privacy:metadata:authorship:component'] = 'Componente a cui si riferisce il record (ad esempio mod_assign).';
$string['privacy:metadata:authorship:final_confidence'] = 'Punteggio finale di fiducia di paternità dopo la valutazione del quiz, se presente.';
$string['privacy:metadata:authorship:final_explanation'] = 'Spiegazione del punteggio finale di fiducia di paternità.';
$string['privacy:metadata:authorship:initial_confidence'] = 'Punteggio iniziale di fiducia di paternità dell\'IA (0–100).';
$string['privacy:metadata:authorship:initial_explanation'] = 'Spiegazione del punteggio iniziale di fiducia di paternità.';
$string['privacy:metadata:authorship:itemid'] = 'Identificatore dell\'elemento nel componente (ad esempio id della consegna).';
$string['privacy:metadata:authorship:quiz_data'] = 'Domande generate del quiz di verifica, memorizzate come JSON.';
$string['privacy:metadata:authorship:quiz_explanation'] = 'Conclusione complessiva dalle risposte del quiz di verifica.';
$string['privacy:metadata:authorship:quiz_grade'] = 'Voto oggettivo del quiz derivato dalle risposte corrette, se presente.';
$string['privacy:metadata:authorship:quiz_question_feedbacks'] = 'Commenti per domanda sulle risposte del quiz di verifica, memorizzati come JSON.';
$string['privacy:metadata:authorship:quiz_responses'] = 'Risposte dello studente al quiz di verifica, memorizzate come JSON.';
$string['privacy:metadata:authorship:response_timings'] = 'Quando è stato avviato il quiz di verifica e quanto è durata ciascuna risposta.';
$string['privacy:metadata:authorship:source_attempt'] = 'Il numero di tentativo della consegna descritta da questo record.';
$string['privacy:metadata:authorship:source_timemodified'] = 'Quando è stata modificata l\'ultima volta la consegna descritta da questo record.';
$string['privacy:metadata:authorship:status'] = 'Fase raggiunta dalla valutazione di paternità.';
$string['privacy:metadata:authorship:timecreated'] = 'Quando è stato creato il record di paternità.';
$string['privacy:metadata:authorship:timemodified'] = 'Quando il record di paternità è stato aggiornato l\'ultima volta.';
$string['privacy:metadata:authorship:userid'] = 'Lo studente a cui appartiene il record di paternità.';
$string['privacy:metadata:dixeo'] = 'Il contenuto della consegna viene inviato al servizio Dixeo tramite il plugin local_dixeo per produrre commenti, punteggi di fiducia di paternità e voti proposti. local_dixeo registra ogni richiesta come un lavoro a nome dell\'utente che l\'ha avviata. Il servizio non memorizza i risultati oltre la durata della richiesta.';
$string['privacy:metadata:dixeo:activity_context'] = 'Il nome dell\'attività, le istruzioni e i criteri di valutazione rispetto ai quali viene valutata la consegna.';
$string['privacy:metadata:dixeo:grading_criteria'] = 'Il metodo di valutazione e i criteri della guida o della rubrica con cui viene valutata la consegna.';
$string['privacy:metadata:dixeo:initial_confidence'] = 'Il punteggio iniziale di fiducia di paternità dello studente, inviato di nuovo per la valutazione finale.';
$string['privacy:metadata:dixeo:quiz_questions'] = 'Le domande del test di verifica generate per lo studente, inviate di nuovo per la valutazione finale.';
$string['privacy:metadata:dixeo:quiz_responses'] = 'Le risposte dello studente al quiz di verifica di paternità.';
$string['privacy:metadata:dixeo:submission_content'] = 'Il testo e gli allegati consegnati dallo studente.';
$string['privacy:metadata:dixeo:submission_files'] = 'I file allegati alla consegna (PDF o testo, al massimo cinque), inviati insieme al testo.';
$string['privacy:metadata:gradefeedback'] = 'Memorizza voti e commenti proposti per una consegna, in attesa della conferma del docente.';
$string['privacy:metadata:gradefeedback:analysis'] = 'Valutazione della consegna visibile solo al personale docente.';
$string['privacy:metadata:gradefeedback:area'] = 'Area all\'interno del componente (ad esempio submission).';
$string['privacy:metadata:gradefeedback:component'] = 'Componente a cui si riferisce il record (ad esempio mod_assign).';
$string['privacy:metadata:gradefeedback:criteria'] = 'Punteggi e osservazioni proposte della guida di valutazione o della griglia, memorizzati come JSON.';
$string['privacy:metadata:gradefeedback:feedback'] = 'Commento proposto per lo studente.';
$string['privacy:metadata:gradefeedback:grade'] = 'Voto numerico proposto, per la valutazione diretta semplice.';
$string['privacy:metadata:gradefeedback:gradingmethod'] = 'Metodo di valutazione per cui è stata prodotta la proposta.';
$string['privacy:metadata:gradefeedback:itemid'] = 'Identificatore dell\'elemento nel componente (ad esempio id della consegna).';
$string['privacy:metadata:gradefeedback:source_attempt'] = 'Il numero di tentativo della consegna descritta da questo record.';
$string['privacy:metadata:gradefeedback:source_timemodified'] = 'Quando è stata modificata l\'ultima volta la consegna descritta da questo record.';
$string['privacy:metadata:gradefeedback:status'] = 'Se un docente ha confermato la proposta.';
$string['privacy:metadata:gradefeedback:timecreated'] = 'Quando è stata creata la proposta.';
$string['privacy:metadata:gradefeedback:timemodified'] = 'Quando la proposta è stata aggiornata l\'ultima volta.';
$string['privacy:metadata:gradefeedback:userid'] = 'Lo studente per cui viene proposto il voto.';
$string['privacy:metadata:plagiarism_dixeo_autograde'] = 'Il docente che ha attivato la valutazione automatica per un compito. I voti automatici di quel compito sono registrati a nome di questo docente.';
$string['privacy:metadata:plagiarism_dixeo_autograde:cmid'] = 'Il compito per cui è stata attivata la valutazione automatica.';
$string['privacy:metadata:plagiarism_dixeo_autograde:timemodified'] = 'Quando è stata attivata la valutazione automatica.';
$string['privacy:metadata:plagiarism_dixeo_autograde:userid'] = 'Il docente che ha attivato la valutazione automatica.';
$string['privacy:metadata:presubmission'] = 'Memorizza il commento che uno studente ha ricevuto su una bozza prima della consegna.';
$string['privacy:metadata:presubmission:area'] = 'Area all\'interno del componente (ad esempio submission).';
$string['privacy:metadata:presubmission:component'] = 'Componente a cui si riferisce il record (ad esempio mod_assign).';
$string['privacy:metadata:presubmission:feedback'] = 'Commento sulla consegna in bozza.';
$string['privacy:metadata:presubmission:itemid'] = 'Identificatore dell\'elemento nel componente (ad esempio id della consegna).';
$string['privacy:metadata:presubmission:source_attempt'] = 'Il numero di tentativo della consegna descritta da questo record.';
$string['privacy:metadata:presubmission:source_timemodified'] = 'Quando è stata modificata l\'ultima volta la consegna descritta da questo record.';
$string['privacy:metadata:presubmission:timecreated'] = 'Quando è stato creato il commento.';
$string['privacy:metadata:presubmission:timemodified'] = 'Quando il commento è stato aggiornato l\'ultima volta.';
$string['privacy:metadata:presubmission:userid'] = 'Lo studente a cui appartiene il commento.';
$string['privacy:path:authorship'] = 'Verifica di paternità';
$string['privacy:path:gradefeedback'] = 'Voti proposti';
$string['privacy:path:presubmission'] = 'Commento precedente alla consegna';
$string['question_locked'] = 'Domanda bloccata';
$string['question_locked_tooltip'] = 'Questa domanda non può essere modificata';
$string['quiz_expired'] = 'Il tempo è scaduto. Le risposte date finora sono state inviate.';
$string['quiz_not_available'] = 'Questo quiz di verifica non è disponibile.';
$string['quiz_not_started'] = 'Il test di verifica non è stato avviato. Avvialo prima di rispondere.';
$string['quiz_payload_invalid'] = 'Il quiz di verifica ha inviato dati illeggibili ({$a}). Ricarica la pagina e riprova.';
$string['quiz_payload_too_large'] = 'Il quiz di verifica ha inviato troppi dati ({$a}). Accorcia la tua risposta.';
$string['quiz_time_remaining'] = 'Tempo rimanente: {$a}';
$string['settings_category_assignment'] = 'Compito';
$string['settings_category_plagiarism_dixeo'] = 'Plagio';
$string['settings_form_label'] = 'Apre le impostazioni Dixeo di questo compito.';
$string['settings_subcategory_authorship'] = 'Verifica di paternità';
$string['settings_subcategory_feedback'] = 'Feedback';
$string['settings_subcategory_grading'] = 'Valutazione';
$string['show_final_score'] = 'Punteggio finale';
$string['show_final_score_hide'] = 'Nascondi allo studente';
$string['show_final_score_score'] = 'Mostra punteggio';
$string['show_final_score_scoreandfeedback'] = 'Mostra punteggio e commento';
$string['show_initial_score'] = 'Punteggio iniziale';
$string['show_initial_score_hide'] = 'Nascondi allo studente';
$string['show_initial_score_score'] = 'Mostra punteggio';
$string['show_initial_score_scoreandfeedback'] = 'Mostra punteggio e commento';
$string['show_test_score'] = 'Punteggio del test';
$string['show_test_score_hide'] = 'Nascondi allo studente';
$string['show_test_score_score'] = 'Mostra punteggio';
$string['show_test_score_scoreandfeedback'] = 'Mostra punteggio e commento';
$string['status_failed_badge'] = 'Analisi non riuscita';
$string['status_pending_badge'] = 'Analisi in corso...';
$string['student_disclosure'] = 'Informazioni per lo studente';
$string['student_disclosure_help'] = 'Testo mostrato agli studenti nella pagina di consegna di qualsiasi compito che usa il feedback sul compito, la verifica di paternità o la valutazione. Usalo per spiegare che il loro lavoro verrà inviato a Dixeo per l\'analisi. Lascia vuoto per non mostrare nulla.';
$string['task_analyze_submission'] = 'Analizza una consegna di compito con Dixeo';
$string['task_apply_draft_grade'] = 'Applica un voto Dixeo come docente';
$string['task_cleanup_orphan_records'] = 'Elimina le analisi Dixeo delle consegne rimosse';
$string['task_retry_pending_analysis'] = 'Riprova le analisi Dixeo incomplete delle consegne';
$string['task_stage_draft_grade'] = 'Prepara una bozza di voto Dixeo';
$string['timer_question_tooltip'] = 'La domanda successiva verrà mostrata quando questo timer scade.';
$string['timer_test_tooltip'] = 'Il test verrà inviato automaticamente quando questo timer scade.';
$string['true'] = 'Vero';
$string['verification_in_progress_badge'] = 'Verifica in corso';
$string['verification_next'] = 'Avanti';
$string['verification_previous'] = 'Indietro';
$string['verification_required_badge'] = 'Verifica richiesta';
$string['verification_submit'] = 'Invia';
$string['verification_test'] = 'Richiedi un quiz di verifica';
$string['verification_test_help'] = 'Quando il punteggio di fiducia iniziale è sotto la soglia, genera un breve quiz sulla consegna che lo studente deve completare. Disattiva questo per registrare il punteggio senza fare domande.';
