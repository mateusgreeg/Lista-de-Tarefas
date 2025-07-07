<?php
// PHP_EOL é uma constante que representa a quebra de linha correta para o sistema operacional.
// É mais robusto do que "\n" ou "\r\n" isoladamente.

// 1. Iniciar a sessão (SEMPRE no topo, antes de qualquer output HTML)
// Usado para persistir dados temporariamente por usuário, se necessário no futuro.
// No nosso caso de arquivo de texto, não é estritamente necessário, mas boa prática.
session_start();

// 2. Definir o caminho do arquivo da lista
$arquivo_lista = 'lista_de_tarefas.txt'; // Alterado para um nome mais específico

// Variáveis de controle e mensagem
$mensagem_status = ''; // Mensagem a ser exibida ao usuário (sucesso/erro/aviso)
$mostrar_confirmacao_apagar_tudo = false; // Flag para controlar a exibição do diálogo de confirmação

// 3. Processamento das Ações (adicionar, apagar tudo, apagar item, marcar/desmarcar)
// O PHP verifica qual ação foi solicitada via POST e age de acordo.
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Ação: Adicionar Novo Item ---
    // Verifica se a ação é 'adicionar_item' E se o campo 'item' foi preenchido.
    if (isset($_POST['action']) && $_POST['action'] == 'adicionar_item') {
        if (isset($_POST['item']) && !empty(trim($_POST['item']))) {
            $novo_item_texto = trim($_POST['item']); // Pega o valor do campo 'item' e remove espaços extras

            // Formato de salvamento: "status|texto do item" (ex: "0|Comprar pão")
            // 0 = não concluído, 1 = concluído. Novos itens começam como não concluídos.
            $linha_para_salvar = "0|" . $novo_item_texto;

            // Tenta abrir o arquivo no modo de adição ('a')
            $handle = @fopen($arquivo_lista, 'a'); // O '@' silencia avisos de erro de permissão

            if ($handle) {
                fwrite($handle, $linha_para_salvar . PHP_EOL); // Escreve a linha e adiciona uma quebra
                fclose($handle); // Fecha o arquivo
                $mensagem_status = "Item '" . htmlspecialchars($novo_item_texto) . "' adicionado com sucesso!";
            } else {
                $mensagem_status = "Erro: Não foi possível adicionar o item ao arquivo. Verifique as permissões de escrita na pasta.";
            }
        } else {
            $mensagem_status = "Por favor, digite um item para adicionar.";
        }
    }

    // --- Ação: Iniciar Confirmação para Apagar Tudo ---
    // Quando o usuário clica no botão "Apagar TUDO" pela primeira vez.
    elseif (isset($_POST['action']) && $_POST['action'] == 'confirmar_apagar_tudo_prompt') {
        $mostrar_confirmacao_apagar_tudo = true; // Ativa a flag para mostrar o diálogo de confirmação
        $mensagem_status = "Tem certeza que deseja apagar TODOS os itens da lista? Esta ação é irreversível.";
    }

    // --- Ação: Apagar TUDO (Após Confirmação) ---
    // Quando o usuário clica no botão "Sim, Apagar TUDO!" no diálogo de confirmação.
    elseif (isset($_POST['action']) && $_POST['action'] == 'apagar_tudo_confirmado') {
        if (file_exists($arquivo_lista)) {
            if (@unlink($arquivo_lista)) { // Tenta apagar o arquivo. '@' silencia avisos.
                $mensagem_status = "Todos os itens foram apagados com sucesso!";
            } else {
                $mensagem_status = "Erro: Não foi possível apagar o arquivo da lista. Verifique as permissões.";
            }
        } else {
            $mensagem_status = "A lista já está vazia. Não há nada para apagar.";
        }
    }
    // --- Ação: Cancelar Confirmação de Apagar Tudo ---
    // Se o usuário clica em "Não, Cancelar".
    elseif (isset($_POST['action']) && $_POST['action'] == 'cancelar_confirmacao') {
        $mostrar_confirmacao_apagar_tudo = false; // Desativa a flag, voltando à visualização normal.
        $mensagem_status = "Exclusão cancelada.";
    }


    // --- Ação: Apagar Item Individual ---
    // Quando o usuário clica no botão "Apagar" ao lado de um item.
    elseif (isset($_POST['action']) && $_POST['action'] == 'apagar_item_individual') {
        // Pega o índice do item a ser apagado, garantindo que é um número inteiro.
        if (isset($_POST['item_index']) && is_numeric($_POST['item_index'])) {
            $index_para_apagar = (int)$_POST['item_index'];

            $itens_atuais = []; // Array para armazenar os itens lidos do arquivo
            if (file_exists($arquivo_lista)) {
                // Lê cada linha do arquivo para um array.
                // FILE_IGNORE_NEW_LINES: Remove a quebra de linha do final de cada elemento.
                // FILE_SKIP_EMPTY_LINES: Não inclui linhas vazias no array.
                $itens_atuais = file($arquivo_lista, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            }

            // Verifica se o índice existe no array antes de tentar remover.
            if (isset($itens_atuais[$index_para_apagar])) {
                // Opcional: Pega o texto do item antes de remover para a mensagem de status.
                // Separa o status e o texto do item para a mensagem.
                list($status_temp, $item_removido_texto) = explode('|', $itens_atuais[$index_para_apagar], 2);

                unset($itens_atuais[$index_para_apagar]); // Remove o item do array pelo índice.

                // Reindexa o array para evitar buracos (ex: [0] [2] [3] viraria [0] [1] [2])
                // Isso é importante para que os índices do HTML correspondam aos do PHP.
                $itens_atuais = array_values($itens_atuais);

                // Agora, reescrevemos o arquivo inteiro com os itens restantes.
                if (!empty($itens_atuais)) {
                    // 'w' abre o arquivo para escrita, apagando o conteúdo existente (sobrescrevendo).
                    $handle = @fopen($arquivo_lista, 'w');
                    if ($handle) {
                        foreach ($itens_atuais as $item_linha) {
                            fwrite($handle, $item_linha . PHP_EOL); // Escreve cada item de volta.
                        }
                        fclose($handle);
                        $mensagem_status = "Item '" . htmlspecialchars($item_removido_texto) . "' apagado com sucesso!";
                    } else {
                        $mensagem_status = "Erro: Não foi possível reescrever o arquivo após remover o item. Verifique as permissões.";
                    }
                } else {
                    // Se não sobrou nenhum item no array, significa que a lista está vazia.
                    // Apagamos o arquivo para indicar que não há itens.
                    if (@unlink($arquivo_lista)) {
                        $mensagem_status = "Último item apagado e lista agora está vazia.";
                    } else {
                        $mensagem_status = "Erro: Não foi possível apagar o arquivo da lista vazia.";
                    }
                }
            } else {
                $mensagem_status = "Erro: Item a ser apagado não encontrado (índice inválido).";
            }
        } else {
            $mensagem_status = "Erro: Requisição inválida para apagar item individual.";
        }
    }

    // --- Ação: Marcar/Desmarcar Item como Concluído ---
    // Acionada pelo checkbox.
    elseif (isset($_POST['action']) && $_POST['action'] == 'marcar_item') {
        // Verifica se o índice do item e o novo estado (0 ou 1) foram enviados.
        if (isset($_POST['item_index']) && is_numeric($_POST['item_index']) && isset($_POST['novo_estado'])) {
            $index_para_marcar = (int)$_POST['item_index'];
            // Garante que o novo estado seja '0' (não concluído) ou '1' (concluído).
            $novo_estado = ($_POST['novo_estado'] === '1') ? '1' : '0';

            $itens_atuais = [];
            if (file_exists($arquivo_lista)) {
                $itens_atuais = file($arquivo_lista, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            }

            // Verifica se o índice do item existe.
            if (isset($itens_atuais[$index_para_marcar])) {
                // Divide a linha atual em status e texto.
                list($estado_antigo, $texto_item) = explode('|', $itens_atuais[$index_para_marcar], 2);

                // Atualiza a linha no array com o novo estado.
                $itens_atuais[$index_para_marcar] = $novo_estado . '|' . $texto_item;

                // Reescreve o arquivo completo com o item atualizado.
                $handle = @fopen($arquivo_lista, 'w'); // 'w' para sobrescrever
                if ($handle) {
                    foreach ($itens_atuais as $item_linha) {
                        fwrite($handle, $item_linha . PHP_EOL);
                    }
                    fclose($handle);
                    $mensagem_status = "Status do item '" . htmlspecialchars($texto_item) . "' atualizado!";
                } else {
                    $mensagem_status = "Erro: Não foi possível reescrever o arquivo para atualizar o status.";
                }
            } else {
                $mensagem_status = "Erro: Item a ser marcado/desmarcado não encontrado (índice inválido).";
            }
        } else {
            $mensagem_status = "Erro: Dados inválidos para marcar/desmarcar item.";
        }
    }

} // Fim do if ($_SERVER["REQUEST_METHOD"] == "POST")

// 4. Carregar os itens da lista para exibição (sempre carrega o estado atual do arquivo)
// Este array conterá objetos ou arrays associativos para cada item (status e texto).
$itens_da_lista_para_exibir = array();
if (file_exists($arquivo_lista)) {
    $linhas = file($arquivo_lista, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        // Tenta dividir a linha em 'status' e 'texto'.
        // Se a linha não tiver '|' (e.g., é um item antigo ou malformado),
        // assume que não está concluído e pega a linha inteira como texto.
        $partes = explode('|', $linha, 2);
        if (count($partes) === 2) {
            $status_concluido = (trim($partes[0]) === '1') ? true : false;
            $texto_do_item = trim($partes[1]);
        } else {
            // Caso a linha não siga o formato 'status|texto', assume não concluído.
            $status_concluido = false;
            $texto_do_item = trim($linha);
        }
        $itens_da_lista_para_exibir[] = ['status' => $status_concluido, 'texto' => $texto_do_item];
    }
}

// O valor do campo de entrada de novo item (para manter o texto digitado em caso de erro, por exemplo)
// Mas vamos limpar ele após a adição para uma melhor UX.
$valor_campo_novo_item = '';
if (isset($_POST['action']) && $_POST['action'] == 'adicionar_item' && isset($_POST['item']) && !empty(trim($_POST['item']))) {
    // Se a adição foi bem-sucedida, limpamos o campo na próxima renderização.
    // Se houve erro (ex: campo vazio), podemos manter o valor digitado.
    if ($mensagem_status == "Por favor, digite um item para adicionar.") {
         $valor_campo_novo_item = htmlspecialchars(trim($_POST['item']));
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Lista de Tarefas</title>
    <meta charset="utf-8">
    <link rel="icon" href="./favicon/favicon.ico" type="image/x-icon">
    <style type="text/css">
        /*
         * Estilos CSS para compatibilidade máxima com navegadores antigos (IE6+, Windows CE)
         * Evitamos CSS3 avançado (gradientes, box-shadow múltiplos, flexbox complexo)
         * e focamos em propriedades básicas e robustas.
         */
        body {
            font-family: Arial, sans-serif; /* Arial é comum em Windows */
            font-size: 14px; /* Tamanho base da fonte */
            background-color: #f0f0f0; /* Fundo cinza claro */
            margin: 5px;
            padding: 0;
            color: #333; /* Cor de texto padrão */
        }
        .container {
            background-color: #ffffff; /* Fundo branco para o container principal */
            padding: 5px;
            border: 2px solid #c0c0c0; /* Borda simples */
            /* Simulação de sombra suave com bordas mais escuras se box-shadow não for suportado */
            border-right: 1px solid #a0a0a0;
            border-bottom: 1px solid #a0a0a0;
            width: 960px; /* Largura fixa para PDAs e monitores de baixa resolução */
            margin: 10px left; /* Centraliza o container */
        }
        h1, h2 {
            color: #222; /* Títulos mais escuros */
            font-size: 14px;
            border-bottom: 1px solid #ddd; /* Linha divisória */
            padding-bottom: 2px;
            margin-top: 5px;
            margin-bottom: 5px;
        }
        h1 {
            font-size: 16px;
        }
        label {
            display: block; /* Cada label em sua própria linha */
            margin-bottom: 3px;
            font-weight: bold;
        }
        .campo-texto {
            width: 95%; /* Quase 100% menos padding */
            padding: 5px;
            margin-bottom: 5px;
            border: 1px solid #808080; /* Borda cinza escura para input */
            background-color: #ffffff;
            font-size: 12px;
            color: #333;
        }

        /* Mensagens de status */
        .mensagem-status {
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid;
            font-size: 13px;
        }
        .mensagem-status.normal {
            background-color: #eafbea; /* Verde claro */
            border-color: #aae0bb;
            color:rgb(0, 0, 0);
        }
        .mensagem-status.erro {
            background-color: #fbeaea; /* Vermelho claro */
            border-color: #e0aeb1;
            color: #a94442;
        }
        .mensagem-status.aviso {
            background-color: #fffacd; /* Amarelo claro */
            border-color: #faebcc;
            color:rgb(196, 207, 110);
        }

        /* Estilos da lista ordenada */
        ol {
            list-style-type: decimal; /* Números para a lista */
            padding-left: 20px; /* Recuo da lista */
            margin-top: 10px;
            margin-bottom: 10px;
        }
        ol li {
            background-color: #fefefe;
            margin-bottom: 5px;
            padding: 8px;
            border: 1px solid #eee;
            /* display: flex; e propriedades relacionadas a flexbox podem não ser suportadas em IE6/CE.
               Usaremos float para alinhar o botão à direita e clear para corrigir. */
            overflow: hidden; /* Clearfix para o float */
            line-height: 1.5; /* Espaçamento entre linhas para o texto */
        }
        /* Nova classe para itens concluídos */
        ol li.concluido {
            color: rgb(3,3,3); /* Texto um pouco mais cinza */
            text-decoration: line-through; /* O efeito tachado! */
        }

        /* Formulário de apagar/marcar individual */
        ol li .form-acao-item {
            float: right; /* Alinha o formulário à direita */
            margin-left: 10px; /* Espaçamento entre o texto do item e o botão */
            margin-top: -3px; /* Pequeno ajuste vertical */
        }
        ol li .form-acao-item button {
             padding: 2px 5px; /* Botões menores para dentro da lista */
             font-size: 11px;
             margin-left: 5px; /* Espaço entre os botões de apagar e checkbox */
        }
        ol li .item-texto-wrapper {
            /* Para o texto do item ocupar o espaço restante e não quebrar embaixo do botão */
            overflow: hidden; /* Garante que o texto não vaze para o botão flutuante */
            display: block; /* Para que o overflow:hidden funcione corretamente */
        }
        ol li input[type="checkbox"] {
            /* Estilos básicos para o checkbox */
            margin-right: 5px; /* Espaço entre o checkbox e o texto */
            vertical-align: middle; /* Alinha o checkbox com o meio do texto */
        }

        .botoes-gerais {
            margin-top: 20px;
            text-align: right; /* Alinha os botões de apagar para a direita */
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .botoes-gerais form {
            display: inline-block; /* Permite que os formulários de botões fiquem lado a lado */
        }
        .botao-estilo-antigo {
            /* Aparência base */
            display: inline-block; /* Crucial para o link se comportar como um botão */
            padding: 6px 8px; /* Espaçamento interno */
            font-family: Arial, sans-serif; /* Uma fonte comum e legível */
            font-size: 12px; /* Tamanho da fonte, pode ajustar */
            color: rgb(3,3,3); /* Cor do texto, um cinza escuro */
            text-decoration: none; /* Remove o sublinhado padrão do link */
            text-align: center;
            cursor: pointer; /* Indica que é clicável */
            white-space: nowrap; /* Evita que o texto quebre linha */
            
            /* Cores de fundo e borda para o efeito 3D */
            background-color: #D4D0C8; /* Cor de fundo principal, um cinza claro */

            /* Bordas para o efeito de relevo.
            As bordas superior e esquerda são mais claras,
            as bordas inferior e direita são mais escuras. */
            border-top: 1px solid #FFFFFF; /* Branco para a borda superior */
            border-left: 1px solid #FFFFFF; /* Branco para a borda esquerda */
            border-right: 1px solid #808080; /* Cinza médio para a borda direita */
            border-bottom: 1px solid #808080; /* Cinza médio para a borda inferior */

            /* Um pequeno "desfoque" na sombra inferior, se suportado */
            /* Navegadores muito antigos podem ignorar box-shadow */
            /* box-shadow: 1px 1px 0px #404040; */ /* Opcional, pode não renderizar em CE */
        }

        /* Efeito ao passar o mouse (hover) */
        .botao-estilo-antigo:hover {
            background-color: #C0BCB4; /* Ligeiramente mais escuro ao passar o mouse */
            /* Inverte as bordas para simular o "pressionar" */
            border-top: 1px solid #808080;
            border-left: 1px solid #808080;
            border-right: 1px solid #FFFFFF;
            border-bottom: 1px solid #FFFFFF;
        }

        /* Efeito quando o botão é clicado/ativo (se o navegador suportar) */
        .botao-estilo-antigo:active {
            background-color: #B0AC9F;
            border-top: 1px solid #808080;
            border-left: 1px solid #808080;
            border-right: 1px solid #FFFFFF;
            border-bottom: 1px solid #FFFFFF;
            /* Adiciona um pequeno padding para "empurrar" o conteúdo para dentro */
            padding: 6px 7px 3px 7px; /* Top, Right, Bottom, Left */
        }
    </style>

    <script type="text/javascript">
        // Função JavaScript para imprimir uma seção específica da página
        function imprimirSecao() {
            var conteudoParaImprimir = document.getElementById('secaoParaImprimir').innerHTML;
            var janelaImpressao = window.open('', '', 'height=500,width=800'); // Abre uma nova janela
            janelaImpressao.document.write('<html><head><title>Imprimir Tarefas</title>');
            // OPCIONAL: Copie alguns estilos básicos para a janela de impressão
            // Isso garante que a impressão tenha uma aparência decente.
            // É mais compatível do que carregar um arquivo CSS externo em uma nova janela para navegadores antigos.
            janelaImpressao.document.write('<style type="text/css">');
            janelaImpressao.document.write('body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }');
            janelaImpressao.document.write('h2 { font-size: 16px; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; }');
            janelaImpressao.document.write('ol { list-style-type: decimal; padding-left: 25px; margin: 0; }');
            janelaImpressao.document.write('ol li { margin-bottom: 3px; }');
            janelaImpressao.document.write('ol li.concluido { text-decoration: line-through; color: #888; }');
            janelaImpressao.document.write('</style>');
            janelaImpressao.document.write('</head><body>');
            janelaImpressao.document.write(conteudoParaImprimir); // Insere o conteúdo da seção
            janelaImpressao.document.write('</body></html>');
            janelaImpressao.document.close(); // Fecha o documento para que o navegador renderize

            // Tenta imprimir após um pequeno atraso para garantir que o conteúdo foi renderizado
            // O setTimeout é importante para navegadores mais lentos ou mais antigos.
            setTimeout(function() {
                janelaImpressao.print(); // Chama a função de impressão do navegador
                janelaImpressao.close(); // Fecha a janela após a impressão (opcional)
            }, 500); // Atraso de 500 milissegundos
        }
    </script>

</head>
<body>

    <div class="container">
        <h1>Lista de Tarefas</h1>

        <?php
        // Exibe a mensagem de status (se houver)
        if (!empty($mensagem_status)) {
            $class_mensagem = 'normal'; // Padrão
            if (strpos($mensagem_status, "Erro:") !== false || strpos($mensagem_status, "Por favor, digite") !== false) {
                $class_mensagem = 'erro';
            } elseif (strpos($mensagem_status, "Tem certeza") !== false) {
                 $class_mensagem = 'aviso';
            }
            echo '<div class="mensagem-status ' . $class_mensagem . '">' . htmlspecialchars($mensagem_status) . '</div>';
        }
        ?>

        <?php if ($mostrar_confirmacao_apagar_tudo): ?>
            <div style="text-align: center; background-color: #fff8e1; border: 1px solid #ffe082; padding: 15px; margin-bottom: 20px;">
                <p><strong><?php echo htmlspecialchars($mensagem_status); ?></strong></p>
                <form action="index.php" method="post" style="display: inline-block; margin-right: 10px;">
                    <button type="submit" name="action" value="apagar_tudo_confirmado" class="botao-estilo-antigo">Sim</button>
                </form>
                <form action="index.php" method="post" style="display: inline-block;">
                    <button type="submit" name="action" value="cancelar_confirmacao" class="botao-estilo-antigo">Não</button>
                </form>
            </div>
        <?php else: ?>
            <form action="index.php" method="post">
                <label for="itemTexto">Descrição :</label>
                <input type="text" id="itemTexto" name="item" class="campo-texto"
                       value="<?php echo $valor_campo_novo_item; ?>"
                       <?php if(empty($valor_campo_novo_item)) echo 'autofocus'; ?>> 
                       <br>
                       <button type="submit" name="action" value="adicionar_item" class="botao-estilo-antigo">Adicionar</button>
                <form action="index.php" method="post" style="display: inline-block;">
                        <button type="submit" name="action" value="confirmar_apagar_tudo_prompt" class="botao-estilo-antigo">Apagar Tudo</button>
                        <button type="button" class="botao-estilo-antigo" onclick="imprimirSecao();">Imprimir Tarefas</button>
                </form>
                
            </form>

        <div id="secaoParaImprimir">
            <h2>Lista</h2>
            <?php if (!empty($itens_da_lista_para_exibir)): ?>
                <ol>
                    <?php foreach ($itens_da_lista_para_exibir as $index => $item_data): ?>
                         <li class="<?php echo ($item_data['status'] ? 'concluido' : ''); ?>">
                                <div style="display: flex; align-items: center; flex-grow: 1;">
                                    <form action="index.php" method="post" style="display: inline-block; margin-right: 5px;">
                                        <input type="hidden" name="action" value="marcar_item">
                                        <input type="hidden" name="item_index" value="<?php echo $index; ?>">
                                        <input type="hidden" name="novo_estado" value="<?php echo ($item_data['status'] ? '0' : '1'); ?>">
                                        <input type="checkbox" name="checkbox_status" value="1"
                                            <?php echo ($item_data['status'] ? 'checked' : ''); ?>
                                            onchange="this.form.submit();">
                                    </form>
                                    <div class="item-texto-wrapper" style="flex-grow: 1;">
                                        <?php echo htmlspecialchars($item_data['texto']); ?>
                                    </div>
                                    <form action="index.php" method="post" style="display: inline-block;">
                                        <input type="hidden" name="action" value="apagar_item_individual">
                                        <input type="hidden" name="item_index" value="<?php echo $index; ?>">
                                        <button type="submit" class="botao-estilo-antigo">Apagar</button>
                                    </form>
                                </div>
                            </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p>Sem tarefas.</p>
            <?php endif; ?>

        <?php endif; // Fim do if ($mostrar_confirmacao_apagar_tudo) ?>
        </div>
    </div>

</body>
</html>