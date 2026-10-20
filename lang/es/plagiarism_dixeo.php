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
 * Spanish language strings for the Dixeo plagiarism plugin.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activity_not_enabled'] = 'Dixeo no está habilitado para esta tarea.';
$string['analysis_aria_label'] = 'Análisis de IA';
$string['analyze'] = 'Analizar';
$string['analyzing'] = 'Analizando...';
$string['assign_trigger'] = 'Cuándo analizar las entregas de la tarea';
$string['assign_trigger_help'] = 'Analizar cada borrador que suba el estudiante o esperar a que entregue para calificar. Analizar al subir da una respuesta más rápida, pero consume más créditos.';
$string['assign_trigger_submit'] = 'Solo en la entrega final';
$string['assign_trigger_upload'] = 'En cada subida (borrador)';
$string['assignment_feedback_enabled'] = 'Comentarios de la tarea';
$string['assignment_feedback_enabled_help'] = 'Analizar las entregas de la tarea y mostrar a los estudiantes el texto informativo. Cada tarea conserva su propia copia de este interruptor.';
$string['authorship_confirm_statement'] = 'Confirmo que este texto es de mi autoría';
$string['authorship_enabled'] = 'Activar la verificación de autoría';
$string['authorship_enabled_desc'] = 'Si está activado, el texto de la entrega puede evaluarse con una puntuación de confianza de autoría. Por debajo del umbral, puede exigirse un cuestionario de verificación.';
$string['authorship_error_missing_record'] = 'Faltan los datos de verificación. Cancele e inténtelo de nuevo.';
$string['authorship_quiz_load_error'] = 'No se ha podido cargar el contenido de verificación.';
$string['authorship_quiz_modal_title'] = 'Verificación requerida';
$string['authorship_submit_quiz'] = 'Enviar verificación';
$string['authorship_threshold'] = 'Umbral de confianza';
$string['authorship_threshold_help'] = 'Puntuación 0–100. Si la confianza inicial es inferior a este valor, se exige un cuestionario de verificación. Use 0 para exigirlo siempre y 100 para no exigirlo nunca. Valor predeterminado: 75.';
$string['authorship_verifying'] = 'Verificando...';
$string['automation_authorship_failed'] = 'No se ha podido completar la comprobación de autoría: {$a}';
$string['automation_authorship_incomplete'] = 'La comprobación de autoría no ha terminado a tiempo (paso: {$a}).';
$string['automation_grade_failed'] = 'No se ha podido preparar la calificación borrador: {$a}';
$string['automation_grade_incomplete'] = 'La calificación borrador no ha terminado a tiempo.';
$string['automation_review_failed'] = 'No se ha podido completar el análisis de la entrega: {$a}';
$string['automation_review_incomplete'] = 'El análisis de la entrega no ha terminado a tiempo.';
$string['close'] = 'Cerrar';
$string['display_final_score'] = 'Puntuación final: {$a} %';
$string['display_final_score_visibility_help'] = 'Mostrar al estudiante la puntuación final de autoría. Con «Mostrar puntuación y comentario» también se muestra cómo se combinaron la puntuación inicial y la prueba de verificación. Es la única puntuación que se muestra cuando no se ha generado una prueba de verificación.';
$string['display_initial_score'] = 'Puntuación inicial: {$a} %';
$string['display_initial_score_visibility_help'] = 'Mostrar la puntuación inicial de autoría, obtenida al leer el contenido entregado. Con «Mostrar puntuación y comentario» también se muestra la explicación. Solo se aplica cuando se ha generado una prueba de verificación.';
$string['display_test_score'] = 'Puntuación de la prueba: {$a} %';
$string['display_test_score_visibility_help'] = 'Mostrar al estudiante la nota de la prueba de verificación. Con «Mostrar puntuación y comentario» también se muestra cómo se calificó la prueba. Solo se aplica cuando se ha completado una prueba de verificación.';
$string['dixeo:mod_assign_grade_submission'] = 'Calificar la tarea con IA';
$string['dixeo:mod_assign_pre_submission'] = 'Obtener comentarios sobre la tarea antes de entregar';
$string['enable_auto_grading'] = 'Aplicar las calificaciones automáticamente';
$string['enable_auto_grading_help'] = 'Si está activado, se escriben automáticamente una calificación y un comentario en el libro de calificaciones cuando el estudiante entrega. Después puede cambiar la calificación. Si está desactivado, no se escribe nada hasta que use el botón Calificar en la vista de evaluación.';
$string['evaluation_mode'] = 'Modo de evaluación inicial';
$string['evaluation_mode_async'] = 'Asíncrono (tarea en cola)';
$string['evaluation_mode_help'] = 'Síncrono: ejecutar el análisis al entregar (puede ralentizar la página). Asíncrono: poner el trabajo en cola y procesarlo en segundo plano. Un fallo síncrono se registra y se reintenta más tarde, sin bloquear la entrega.';
$string['evaluation_mode_sync'] = 'Síncrono (en el observador)';
$string['event_analysis_requested'] = 'Entrega enviada para análisis';
$string['event_authorship_checked'] = 'Evaluación de autoría registrada';
$string['event_grade_draft_applied'] = 'Calificación borrador aplicada';
$string['event_grade_draft_created'] = 'Calificación borrador preparada';
$string['event_verification_started'] = 'Verificación de autoría iniciada';
$string['event_verification_submitted'] = 'Verificación de autoría enviada';
$string['false'] = 'Falso';
$string['grade_draft_notice'] = 'Dixeo ha rellenado abajo una calificación y un comentario sugeridos. No se ha guardado nada: revise la sugerencia, cambie lo que no le convenza y guarde el formulario usted mismo.';
$string['grading_ai_disclaimer'] = 'Este comentario se ha generado con IA.';
$string['grading_enabled'] = 'Calificación';
$string['grading_enabled_help'] = 'Permitir la calificación asistida y las calificaciones automáticas en las tareas. Cada tarea conserva su propia copia de este interruptor.';
$string['grading_not_enabled'] = 'La calificación está desactivada para esta tarea.';
$string['job_poll_timeout'] = 'El análisis está tardando demasiado. Inténtelo de nuevo.';
$string['panel_maximize'] = 'Maximizar';
$string['panel_minimize'] = 'Minimizar';
$string['pluginname'] = 'Dixeo';
$string['privacy:metadata:authorship'] = 'Almacena comprobaciones de confianza de autoría y datos opcionales del cuestionario de verificación de las entregas de tareas.';
$string['privacy:metadata:authorship:area'] = 'Área dentro del componente (por ejemplo submission).';
$string['privacy:metadata:authorship:component'] = 'Componente al que se refiere el registro (por ejemplo mod_assign).';
$string['privacy:metadata:authorship:final_confidence'] = 'Puntuación final de confianza de autoría tras evaluar el cuestionario, si la hay.';
$string['privacy:metadata:authorship:final_explanation'] = 'Explicación de la puntuación final de confianza de autoría.';
$string['privacy:metadata:authorship:initial_confidence'] = 'Puntuación inicial de confianza de autoría de la IA (0–100).';
$string['privacy:metadata:authorship:initial_explanation'] = 'Explicación de la puntuación inicial de confianza de autoría.';
$string['privacy:metadata:authorship:itemid'] = 'Identificador del elemento en el componente (por ejemplo id de entrega).';
$string['privacy:metadata:authorship:quiz_data'] = 'Preguntas generadas del cuestionario de verificación, almacenadas como JSON.';
$string['privacy:metadata:authorship:quiz_explanation'] = 'Conclusión global a partir de las respuestas del cuestionario de verificación.';
$string['privacy:metadata:authorship:quiz_grade'] = 'Nota objetiva del cuestionario derivada de las respuestas correctas, si la hay.';
$string['privacy:metadata:authorship:quiz_question_feedbacks'] = 'Comentarios por pregunta sobre las respuestas del cuestionario de verificación, almacenados como JSON.';
$string['privacy:metadata:authorship:quiz_responses'] = 'Respuestas del estudiante al cuestionario de verificación, almacenadas como JSON.';
$string['privacy:metadata:authorship:response_timings'] = 'Cuándo se inició el cuestionario de verificación y cuánto duró cada respuesta.';
$string['privacy:metadata:authorship:status'] = 'Etapa alcanzada por la evaluación de autoría.';
$string['privacy:metadata:authorship:timecreated'] = 'Cuándo se creó el registro de autoría.';
$string['privacy:metadata:authorship:timemodified'] = 'Cuándo se actualizó por última vez el registro de autoría.';
$string['privacy:metadata:authorship:userid'] = 'El estudiante al que pertenece el registro de autoría.';
$string['privacy:metadata:dixeo'] = 'El contenido de la entrega se envía al servicio Dixeo para producir comentarios, puntuaciones de confianza de autoría y calificaciones propuestas. El servicio no almacena resultados más allá de la duración de la solicitud.';
$string['privacy:metadata:dixeo:activity_context'] = 'El nombre de la actividad, las instrucciones y los criterios de calificación con los que se evalúa la entrega.';
$string['privacy:metadata:dixeo:quiz_responses'] = 'Las respuestas del estudiante al cuestionario de verificación de autoría.';
$string['privacy:metadata:dixeo:submission_content'] = 'El texto y los archivos adjuntos que entregó el estudiante.';
$string['privacy:metadata:gradefeedback'] = 'Almacena calificaciones y comentarios propuestos para una entrega, pendientes de confirmación del profesorado.';
$string['privacy:metadata:gradefeedback:analysis'] = 'Evaluación de la entrega visible solo para el profesorado.';
$string['privacy:metadata:gradefeedback:area'] = 'Área dentro del componente (por ejemplo submission).';
$string['privacy:metadata:gradefeedback:component'] = 'Componente al que se refiere el registro (por ejemplo mod_assign).';
$string['privacy:metadata:gradefeedback:criteria'] = 'Puntuaciones y observaciones propuestas de la guía de evaluación o rúbrica, almacenadas como JSON.';
$string['privacy:metadata:gradefeedback:feedback'] = 'Comentario propuesto para el estudiante.';
$string['privacy:metadata:gradefeedback:grade'] = 'Calificación numérica propuesta, para calificación directa simple.';
$string['privacy:metadata:gradefeedback:gradingmethod'] = 'Método de calificación para el que se produjo la propuesta.';
$string['privacy:metadata:gradefeedback:itemid'] = 'Identificador del elemento en el componente (por ejemplo id de entrega).';
$string['privacy:metadata:gradefeedback:status'] = 'Si un profesor ha confirmado la propuesta.';
$string['privacy:metadata:gradefeedback:timecreated'] = 'Cuándo se creó la propuesta.';
$string['privacy:metadata:gradefeedback:timemodified'] = 'Cuándo se actualizó por última vez la propuesta.';
$string['privacy:metadata:gradefeedback:userid'] = 'El estudiante para el que se propone la calificación.';
$string['privacy:metadata:presubmission'] = 'Almacena el comentario que un estudiante recibió sobre un borrador antes de entregar.';
$string['privacy:metadata:presubmission:area'] = 'Área dentro del componente (por ejemplo submission).';
$string['privacy:metadata:presubmission:component'] = 'Componente al que se refiere el registro (por ejemplo mod_assign).';
$string['privacy:metadata:presubmission:feedback'] = 'Comentario sobre la entrega en borrador.';
$string['privacy:metadata:presubmission:itemid'] = 'Identificador del elemento en el componente (por ejemplo id de entrega).';
$string['privacy:metadata:presubmission:timecreated'] = 'Cuándo se creó el comentario.';
$string['privacy:metadata:presubmission:timemodified'] = 'Cuándo se actualizó por última vez el comentario.';
$string['privacy:metadata:presubmission:userid'] = 'El estudiante al que pertenece el comentario.';
$string['privacy:path:authorship'] = 'Verificación de autoría';
$string['privacy:path:gradefeedback'] = 'Calificaciones propuestas';
$string['privacy:path:presubmission'] = 'Comentario previo a la entrega';
$string['question_locked'] = 'Pregunta bloqueada';
$string['question_locked_tooltip'] = 'Esta pregunta no se puede editar';
$string['quiz_expired'] = 'Se ha agotado el tiempo. Se han enviado las respuestas hasta ahora.';
$string['quiz_not_available'] = 'Este cuestionario de verificación no está disponible.';
$string['quiz_payload_invalid'] = 'El cuestionario de verificación ha enviado datos ilegibles ({$a}). Recargue la página e inténtelo de nuevo.';
$string['quiz_payload_too_large'] = 'El cuestionario de verificación ha enviado demasiados datos ({$a}). Acorte su respuesta.';
$string['quiz_time_remaining'] = 'Tiempo restante: {$a}';
$string['settings_category_assignment'] = 'Tarea';
$string['settings_category_plagiarism_dixeo'] = 'Plagio';
$string['settings_form_label'] = 'Abre los ajustes de Dixeo de esta tarea.';
$string['settings_subcategory_authorship'] = 'Verificación de autoría';
$string['settings_subcategory_feedback'] = 'Comentarios';
$string['settings_subcategory_grading'] = 'Calificación';
$string['show_final_score'] = 'Puntuación final';
$string['show_final_score_hide'] = 'Ocultar al estudiante';
$string['show_final_score_score'] = 'Mostrar puntuación';
$string['show_final_score_scoreandfeedback'] = 'Mostrar puntuación y comentario';
$string['show_initial_score'] = 'Puntuación inicial';
$string['show_initial_score_hide'] = 'Ocultar al estudiante';
$string['show_initial_score_score'] = 'Mostrar puntuación';
$string['show_initial_score_scoreandfeedback'] = 'Mostrar puntuación y comentario';
$string['show_test_score'] = 'Puntuación de la prueba';
$string['show_test_score_hide'] = 'Ocultar al estudiante';
$string['show_test_score_score'] = 'Mostrar puntuación';
$string['show_test_score_scoreandfeedback'] = 'Mostrar puntuación y comentario';
$string['status_failed_badge'] = 'Análisis fallido';
$string['status_pending_badge'] = 'Analizando...';
$string['student_disclosure'] = 'Información para el estudiante';
$string['student_disclosure_help'] = 'Texto mostrado a los estudiantes en la página de entrega. Explique que su trabajo se analizará y qué implica. Déjelo vacío para no mostrar nada.';
$string['task_analyze_submission'] = 'Analizar una entrega de tarea con Dixeo';
$string['task_retry_pending_analysis'] = 'Reintentar análisis de entregas Dixeo incompletos';
$string['task_stage_draft_grade'] = 'Preparar y aplicar una calificación Dixeo';
$string['timer_question_tooltip'] = 'La siguiente pregunta se mostrará cuando termine este temporizador.';
$string['timer_test_tooltip'] = 'La prueba se enviará automáticamente cuando termine este temporizador.';
$string['true'] = 'Verdadero';
$string['verification_in_progress_badge'] = 'Verificación en curso';
$string['verification_next'] = 'Siguiente';
$string['verification_previous'] = 'Anterior';
$string['verification_required_badge'] = 'Verificación requerida';
$string['verification_submit'] = 'Enviar';
$string['verification_test'] = 'Exigir un cuestionario de verificación';
$string['verification_test_help'] = 'Cuando la puntuación de confianza inicial queda por debajo del umbral, generar un cuestionario breve sobre la entrega que el estudiante debe completar. Desactive esto para registrar la puntuación sin hacer preguntas.';
