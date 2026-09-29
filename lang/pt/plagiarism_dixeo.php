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
 * Portuguese language strings for the Dixeo plagiarism plugin.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activity_not_enabled'] = 'O Dixeo não está ativado para este trabalho.';
$string['allow_backwards_navigation'] = 'Permitir a navegação para trás';
$string['allow_backwards_navigation_help'] = 'Permite que os estudantes voltem às perguntas já ultrapassadas. Se estiver desativado, avançar é definitivo e o botão Seguinte fica brevemente desativado para evitar cliques duplos.';
$string['analysis_aria_label'] = 'Análise de IA';
$string['analyze'] = 'Analisar';
$string['analyzing'] = 'A analisar...';
$string['assign_trigger'] = 'Quando analisar as submissões do trabalho';
$string['assign_trigger_help'] = 'Analisar cada rascunho que o estudante carrega ou esperar que submeta para avaliação. Analisar no carregamento dá um feedback mais rápido, mas consome mais créditos.';
$string['assign_trigger_submit'] = 'Apenas na submissão final';
$string['assign_trigger_upload'] = 'Em cada carregamento (rascunho)';
$string['authorship_cancel'] = 'Cancelar';
$string['authorship_confirm_and_submit'] = 'Confirmar e submeter';
$string['authorship_confirm_statement'] = 'Confirmo que este texto é da minha autoria';
$string['authorship_count_multiple_choice'] = 'Número de perguntas de escolha múltipla';
$string['authorship_count_multiple_choice_help'] = 'Quantas perguntas de escolha múltipla gerar no questionário de verificação (0–5).';
$string['authorship_count_open'] = 'Número de perguntas abertas';
$string['authorship_count_open_help'] = 'Quantas perguntas abertas (texto livre) gerar no questionário de verificação (0–5).';
$string['authorship_count_true_false'] = 'Número de perguntas verdadeiro/falso';
$string['authorship_count_true_false_help'] = 'Quantas perguntas verdadeiro/falso gerar no questionário de verificação (0–5).';
$string['authorship_enabled'] = 'Ativar a verificação de autoria';
$string['authorship_enabled_desc'] = 'Se estiver ativado, o texto da submissão pode ser avaliado com uma pontuação de confiança de autoria. Abaixo do limiar, pode ser exigido um questionário de verificação.';
$string['authorship_error_missing_record'] = 'Faltam os dados de verificação. Cancele e tente novamente.';
$string['authorship_quiz_load_error'] = 'Não foi possível carregar o conteúdo de verificação.';
$string['authorship_quiz_modal_title'] = 'Verificação necessária';
$string['authorship_settings_heading'] = 'Verificação de autoria';
$string['authorship_settings_heading_desc'] = 'Configure quando e como a verificação de autoria (pontuação de confiança e questionário opcional) é aplicada às submissões.';
$string['authorship_submit_quiz'] = 'Enviar verificação';
$string['authorship_threshold'] = 'Limiar de confiança';
$string['authorship_threshold_help'] = 'Pontuação 0–100. Se a confiança inicial for inferior a este valor, é exigido um questionário de verificação. Use 0 para o exigir sempre e 100 para nunca o exigir. Predefinição: 75.';
$string['authorship_threshold_invalid'] = 'Introduza um limiar de confiança entre 0 e 100.';
$string['authorship_verifying'] = 'A verificar...';
$string['automation_authorship_failed'] = 'Não foi possível concluir a verificação de autoria: {$a}';
$string['automation_authorship_incomplete'] = 'A verificação de autoria não terminou a tempo (etapa: {$a}).';
$string['automation_grade_failed'] = 'Não foi possível preparar a nota em rascunho: {$a}';
$string['automation_grade_incomplete'] = 'A nota em rascunho não terminou a tempo.';
$string['automation_review_failed'] = 'Não foi possível concluir a análise da submissão: {$a}';
$string['automation_review_incomplete'] = 'A análise da submissão não terminou a tempo.';
$string['close'] = 'Fechar';
$string['display_final_score'] = 'Pontuação final: {$a} %';
$string['display_final_score_visibility'] = 'Pontuação final';
$string['display_final_score_visibility_help'] = 'Mostrar ao estudante a pontuação final de autoria. Com «Mostrar pontuação e comentário» também é mostrado como a pontuação inicial e o teste de verificação foram combinados. É a única pontuação mostrada quando não foi gerado um teste de verificação.';
$string['display_initial_score'] = 'Pontuação inicial: {$a} %';
$string['display_initial_score_visibility'] = 'Pontuação inicial';
$string['display_initial_score_visibility_help'] = 'Mostrar a pontuação inicial de autoria, obtida da leitura do conteúdo submetido. Com «Mostrar pontuação e comentário» também é mostrada a explicação. Aplica-se apenas quando foi gerado um teste de verificação.';
$string['display_test_score'] = 'Pontuação do teste: {$a} %';
$string['display_test_score_visibility'] = 'Pontuação do teste';
$string['display_test_score_visibility_help'] = 'Mostrar ao estudante a nota do teste de verificação. Com «Mostrar pontuação e comentário» também é mostrado como o teste foi avaliado. Aplica-se apenas quando foi concluído um teste de verificação.';
$string['dixeo:mod_assign_grade_submission'] = 'Avaliar o trabalho com IA';
$string['dixeo:mod_assign_pre_submission'] = 'Obter comentários sobre o trabalho antes de submeter';
$string['enable_auto_grading'] = 'Aplicar as notas automaticamente';
$string['enable_auto_grading_help'] = 'Se estiver ativado, uma nota e um comentário são escritos automaticamente no livro de notas quando o estudante submete. Pode ainda alterar a nota depois. Se estiver desativado, nada é escrito até usar o botão Avaliar na vista de avaliação.';
$string['enable_mod_assign'] = 'Ativar para os trabalhos';
$string['enable_mod_assign_help'] = 'Tornar o Dixeo disponível nas atividades Trabalho. Cada trabalho deve ainda ativá-lo nas suas próprias definições.';
$string['enabled'] = 'Ativar o Dixeo';
$string['enabled_help'] = 'Interruptor principal do plugin. Enquanto estiver desativado, o Dixeo não faz nada no sítio, independentemente das definições de cada atividade.';
$string['evaluation_mode'] = 'Modo de avaliação inicial';
$string['evaluation_mode_async'] = 'Assíncrono (tarefa em fila)';
$string['evaluation_mode_help'] = 'Síncrono: executar a análise na submissão (pode atrasar a página). Assíncrono: colocar o trabalho em fila e processá-lo em segundo plano. Uma falha síncrona é registada e tentada novamente mais tarde, sem bloquear a submissão.';
$string['evaluation_mode_sync'] = 'Síncrono (no observer)';
$string['event_analysis_requested'] = 'Submissão enviada para análise';
$string['event_authorship_checked'] = 'Avaliação de autoria registada';
$string['event_grade_draft_applied'] = 'Nota em rascunho aplicada';
$string['event_grade_draft_created'] = 'Nota em rascunho preparada';
$string['event_verification_started'] = 'Verificação de autoria iniciada';
$string['event_verification_submitted'] = 'Verificação de autoria enviada';
$string['false'] = 'Falso';
$string['grade_draft_notice'] = 'O Dixeo preencheu abaixo uma nota e um comentário sugeridos. Nada foi guardado: reveja a sugestão, altere o que não lhe convencer e guarde o formulário você mesmo.';
$string['grading_ai_disclaimer'] = 'Este comentário foi gerado com IA.';
$string['job_poll_timeout'] = 'A análise está a demorar demasiado. Tente novamente.';
$string['mod_assign_grade_submission'] = 'Avaliar o trabalho com IA';
$string['mod_assign_grade_submission_help'] = 'Permite usar a avaliação assistida por IA ao ver a submissão de um estudante. Destinado apenas ao pessoal docente.';
$string['mod_assign_pre_submission'] = 'Obter comentários sobre o trabalho antes de submeter';
$string['mod_assign_pre_submission_help'] = 'Permite receber comentários sobre a submissão antes de a enviar para avaliação (passo Analisar).';
$string['next_button_delay_invalid'] = 'Introduza um atraso entre 0 e {$a} segundos.';
$string['next_button_delay_seconds'] = 'Atraso do botão Seguinte (segundos)';
$string['next_button_delay_seconds_help'] = 'Durante quanto tempo o botão Seguinte permanece desativado após um clique, para evitar cliques duplos. Aplica-se apenas se a navegação para trás estiver desativada. Predefinição: 3.';
$string['panel_maximize'] = 'Maximizar';
$string['panel_minimize'] = 'Minimizar';
$string['plagiarism_settings_heading'] = 'Geral';
$string['plagiarism_settings_heading_desc'] = 'Controlar onde o Dixeo está disponível e o que é comunicado aos estudantes.';
$string['pluginname'] = 'Dixeo';
$string['privacy:metadata:authorship'] = 'Armazena verificações de confiança de autoria e dados opcionais do questionário de verificação das submissões de trabalhos.';
$string['privacy:metadata:authorship:area'] = 'Área dentro do componente (por exemplo submission).';
$string['privacy:metadata:authorship:component'] = 'Componente a que o registo se refere (por exemplo mod_assign).';
$string['privacy:metadata:authorship:final_confidence'] = 'Pontuação final de confiança de autoria após avaliar o questionário, se existir.';
$string['privacy:metadata:authorship:final_explanation'] = 'Explicação da pontuação final de confiança de autoria.';
$string['privacy:metadata:authorship:initial_confidence'] = 'Pontuação inicial de confiança de autoria da IA (0–100).';
$string['privacy:metadata:authorship:initial_explanation'] = 'Explicação da pontuação inicial de confiança de autoria.';
$string['privacy:metadata:authorship:itemid'] = 'Identificador do item no componente (por exemplo id da submissão).';
$string['privacy:metadata:authorship:quiz_data'] = 'Perguntas geradas do questionário de verificação, armazenadas como JSON.';
$string['privacy:metadata:authorship:quiz_explanation'] = 'Conclusão global a partir das respostas do questionário de verificação.';
$string['privacy:metadata:authorship:quiz_grade'] = 'Nota objetiva do questionário derivada das respostas corretas, se existir.';
$string['privacy:metadata:authorship:quiz_question_feedbacks'] = 'Comentários por pergunta sobre as respostas do questionário de verificação, armazenados como JSON.';
$string['privacy:metadata:authorship:quiz_responses'] = 'Respostas do estudante ao questionário de verificação, armazenadas como JSON.';
$string['privacy:metadata:authorship:response_timings'] = 'Quando o questionário de verificação foi iniciado e quanto durou cada resposta.';
$string['privacy:metadata:authorship:status'] = 'Etapa alcançada pela avaliação de autoria.';
$string['privacy:metadata:authorship:timecreated'] = 'Quando o registo de autoria foi criado.';
$string['privacy:metadata:authorship:timemodified'] = 'Quando o registo de autoria foi atualizado pela última vez.';
$string['privacy:metadata:authorship:userid'] = 'O estudante a quem o registo de autoria pertence.';
$string['privacy:metadata:dixeo'] = 'O conteúdo da submissão é enviado para o serviço Dixeo para produzir comentários, pontuações de confiança de autoria e notas propostas. O serviço não armazena resultados para além da duração do pedido.';
$string['privacy:metadata:dixeo:activity_context'] = 'O nome da atividade, as instruções e os critérios de avaliação em relação aos quais a submissão é avaliada.';
$string['privacy:metadata:dixeo:quiz_responses'] = 'As respostas do estudante ao questionário de verificação de autoria.';
$string['privacy:metadata:dixeo:submission_content'] = 'O texto e os anexos que o estudante submeteu.';
$string['privacy:metadata:gradefeedback'] = 'Armazena notas e comentários propostos para uma submissão, pendentes de confirmação do docente.';
$string['privacy:metadata:gradefeedback:analysis'] = 'Avaliação da submissão visível apenas para o pessoal docente.';
$string['privacy:metadata:gradefeedback:area'] = 'Área dentro do componente (por exemplo submission).';
$string['privacy:metadata:gradefeedback:component'] = 'Componente a que o registo se refere (por exemplo mod_assign).';
$string['privacy:metadata:gradefeedback:criteria'] = 'Pontuações e observações propostas do guia de avaliação ou rubrica, armazenadas como JSON.';
$string['privacy:metadata:gradefeedback:feedback'] = 'Comentário proposto para o estudante.';
$string['privacy:metadata:gradefeedback:grade'] = 'Nota numérica proposta, para avaliação direta simples.';
$string['privacy:metadata:gradefeedback:gradingmethod'] = 'Método de avaliação para o qual a proposta foi produzida.';
$string['privacy:metadata:gradefeedback:itemid'] = 'Identificador do item no componente (por exemplo id da submissão).';
$string['privacy:metadata:gradefeedback:status'] = 'Se um docente confirmou a proposta.';
$string['privacy:metadata:gradefeedback:timecreated'] = 'Quando a proposta foi criada.';
$string['privacy:metadata:gradefeedback:timemodified'] = 'Quando a proposta foi atualizada pela última vez.';
$string['privacy:metadata:gradefeedback:userid'] = 'O estudante para quem a nota é proposta.';
$string['privacy:metadata:presubmission'] = 'Armazena o comentário que um estudante recebeu sobre um rascunho antes de submeter.';
$string['privacy:metadata:presubmission:area'] = 'Área dentro do componente (por exemplo submission).';
$string['privacy:metadata:presubmission:component'] = 'Componente a que o registo se refere (por exemplo mod_assign).';
$string['privacy:metadata:presubmission:feedback'] = 'Comentário sobre a submissão em rascunho.';
$string['privacy:metadata:presubmission:itemid'] = 'Identificador do item no componente (por exemplo id da submissão).';
$string['privacy:metadata:presubmission:timecreated'] = 'Quando o comentário foi criado.';
$string['privacy:metadata:presubmission:timemodified'] = 'Quando o comentário foi atualizado pela última vez.';
$string['privacy:metadata:presubmission:userid'] = 'O estudante a quem o comentário pertence.';
$string['privacy:path:authorship'] = 'Verificação de autoria';
$string['privacy:path:gradefeedback'] = 'Notas propostas';
$string['privacy:path:presubmission'] = 'Comentário prévio à submissão';
$string['question_locked'] = 'Pergunta bloqueada';
$string['question_locked_tooltip'] = 'Esta pergunta não pode ser editada';
$string['quiz_expired'] = 'O tempo esgotou. As respostas dadas até agora foram enviadas.';
$string['quiz_not_available'] = 'Este questionário de verificação não está disponível.';
$string['quiz_payload_invalid'] = 'O questionário de verificação enviou dados ilegíveis ({$a}). Recarregue a página e tente novamente.';
$string['quiz_payload_too_large'] = 'O questionário de verificação enviou demasiados dados ({$a}). Encurte a sua resposta.';
$string['quiz_time_invalid'] = 'Introduza um limite de tempo de no máximo {$a}, ou 0 para sem limite.';
$string['quiz_time_multiple_choice'] = 'Tempo por pergunta de escolha múltipla';
$string['quiz_time_multiple_choice_help'] = 'Quanto tempo o estudante tem para cada pergunta de escolha múltipla. Defina 0 para sem limite. Predefinição: 1 minuto.';
$string['quiz_time_open'] = 'Tempo por pergunta aberta';
$string['quiz_time_open_help'] = 'Quanto tempo o estudante tem para cada pergunta aberta. Defina 0 para sem limite. Predefinição: 3 minutos.';
$string['quiz_time_remaining'] = 'Tempo restante: {$a}';
$string['quiz_time_true_false'] = 'Tempo por pergunta verdadeiro/falso';
$string['quiz_time_true_false_help'] = 'Quanto tempo o estudante tem para cada pergunta verdadeiro/falso. Defina 0 para sem limite. Predefinição: 1 minuto.';
$string['quiz_total_time'] = 'Tempo total do questionário';
$string['quiz_total_time_help'] = 'Quanto tempo o estudante tem para todo o questionário de verificação. O relógio corre no servidor, pelo que continua se a página for fechada e reaberta. Defina 0 para sem limite. Predefinição: 3 minutos.';
$string['setting_value_invalid'] = 'Escolha uma das opções disponíveis.';
$string['status_failed_badge'] = 'Análise falhada';
$string['status_pending_badge'] = 'A analisar...';
$string['student_disclosure'] = 'Informação para o estudante';
$string['student_disclosure_help'] = 'Texto mostrado aos estudantes na página de submissão. Explique que o seu trabalho será analisado e o que isso implica. Deixe vazio para não mostrar nada.';
$string['task_analyze_submission'] = 'Analisar uma submissão de trabalho com o Dixeo';
$string['task_retry_pending_analysis'] = 'Tentar novamente análises Dixeo incompletas de submissões';
$string['task_stage_draft_grade'] = 'Preparar e aplicar uma nota Dixeo';
$string['timer_question_tooltip'] = 'A pergunta seguinte será mostrada quando este temporizador terminar.';
$string['timer_test_tooltip'] = 'O teste será enviado automaticamente quando este temporizador terminar.';
$string['true'] = 'Verdadeiro';
$string['use_dixeo'] = 'Ativar o Dixeo para esta atividade';
$string['use_dixeo_help'] = 'Se estiver ativado, o Dixeo analisa as submissões deste trabalho. Os estudantes podem pedir comentários sobre um rascunho antes de submeter, e as submissões são avaliadas quanto à autoria. Se for exigido um teste de verificação, o estudante deve concluí-lo para que a submissão seja considerada completa.';
$string['verification_in_progress_badge'] = 'Verificação em curso';
$string['verification_next'] = 'Seguinte';
$string['verification_previous'] = 'Anterior';
$string['verification_quiz_timers_desc'] = 'Limites de tempo do questionário de verificação. O servidor mantém o relógio, pelo que fechar a página não o para. O tempo em cada pergunta é registado e enviado para a avaliação.';
$string['verification_quiz_timers_heading'] = 'Tempo e navegação do questionário de verificação';
$string['verification_required_badge'] = 'Verificação necessária';
$string['verification_test'] = 'Exigir um questionário de verificação';
$string['verification_test_help'] = 'Quando a pontuação de confiança inicial fica abaixo do limiar, gerar um questionário breve sobre a submissão que o estudante deve concluir. Desative isto para registar a pontuação sem fazer perguntas.';
$string['visibility_hide'] = 'Ocultar do estudante';
$string['visibility_score'] = 'Mostrar pontuação';
$string['visibility_score_and_feedback'] = 'Mostrar pontuação e comentário';
