<?php
//Configura o servidor MySQL
$servername = 'serverIP';
$username = 'username';
$password = 'passwd';
$database = 'database';



//Configura o fuso horário do servidor
date_default_timezone_set("America/Sao_Paulo");
//Configura o tempo limite de execução (infinito)
set_time_limit(0);
$timeLog = fopen('time.txt', 'a+');
$teste = fopen("teste.txt", "a+");
$dataLog = fopen('data.txt', 'a+');
fwrite($dataLog, "[");

//Classe de ativos, utilizada na chamada da api que lista todos os ativos ('https://api-cotacao-b3.labdo.it/api/empresa')
class ativos_API1
{
    public $nm_empresa = '';
    public $cd_acao = '';
    public $curPrc = 0.0;
    public $prcFlcn = 0.0;
    public $fechamentoQuinzenal = array();
    function addToArray(float $valorFechamento)
    {
        array_push($this->fechamentoQuinzenal, $valorFechamento);
    }

    function __construct(string $nm_empresa)
    {
        $this->nm_empresa = $nm_empresa;
    }
    function setCdAcao($cd_acao)
    {
        $this->cd_acao = $cd_acao;
    }
    function setCurPrcFlcn(float $curPrc, float $prcFlcn)
    {
        $this->curPrc = $curPrc;
        $this->prcFlcn = $prcFlcn;
    }
    function deleteContentFechamento()
    {
        foreach ($this->fechamentoQuinzenal as $i) {
            array_pop($this->fechamentoQuinzenal);
        }
    }
}

function listaAtivos()
{
    $log2 = fopen("log2.txt", 'a+');
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api-cotacao-b3.labdo.it/api/empresa',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
    ));

    //trata a saída da requisição
    $response = curl_exec($curl);
    $response = json_decode($response);
    curl_close($curl);
    // $log = fopen("log.txt", 'a+');
    //Tratamento do atributo cd_acao
    foreach ($response as $acao) {
        if ($acao->cd_acao != "") {
            $acaoObj = new ativos_API1($acao->nm_empresa);
            //divide as ações de empresas que possuem mais de um ativo
            $cd_acao_array = explode(', ', $acao->cd_acao);
            $count = count($cd_acao_array);


            foreach ($cd_acao_array as $n) {
                $acaoObj->setCdAcao($n);
                fwrite($log2, json_encode($acaoObj));
                fwrite($log2, "\n");
                precoAtivos($acaoObj);
                fechamentoAtivos($acaoObj);
            }

            // else {
            //     fwrite($log2, json_encode($acaoObj));
            //     fwrite($log2, "\n");
            //     precoAtivos($acaoObj);
            //     fechamentoAtivos($acaoObj);
            // }
        }
    }
}

function precoAtivos($acao)
{
    $log = fopen("log.txt", 'a+');
    $curl = curl_init();
    // $acao = json_encode($acao->cd_acao);
    //fwrite($log, $acao->cd_acao . '---');
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://cotacao.b3.com.br/mds/api/v1/InstrumentQuotation/' . $acao->cd_acao,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
    ));

    $response = curl_exec($curl);
    //fwrite($log, $response);
    curl_close($curl);
    $response = json_decode($response);
    if (isset($response->Trad) && isset($response->Trad[0]->scty->SctyQtn->prcFlcn) && isset($response->Trad[0]->scty->SctyQtn->curPrc)) {
        $acao->setCurPrcFlcn($response->Trad[0]->scty->SctyQtn->curPrc, $response->Trad[0]->scty->SctyQtn->prcFlcn);
        fwrite($log, $acao->cd_acao);
        fwrite($log, " ---> ");
        fwrite($log, json_encode([$response->Trad[0]->scty->SctyQtn->curPrc, $response->Trad[0]->scty->SctyQtn->prcFlcn]));
        fwrite($log, "\n");
        fwrite($log, json_encode($acao));
        fwrite($log, "\n");

        $acao->setCurPrcFlcn($response->Trad[0]->scty->SctyQtn->curPrc, $response->Trad[0]->scty->SctyQtn->prcFlcn);

        // fwrite($dataLog, json_encode($acao));
        // fwrite($dataLog, "\n");
        // fwrite($log, $response->Trad[0]->scty->SctyQtn->curPrc, $response->Trad[0]->scty->SctyQtn->prcFlcn);
        // fwrite($log, "\n");
    }
}

function fechamentoAtivos($acao)
{
    $dataLog = fopen('data.txt', 'a+');
    $fechamento = fopen("dataFechamento.txt", "a+");
    $log = fopen("log3.txt", 'a+');
    $curl = curl_init();
    //fwrite($log, "\n");
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api-cotacao-b3.labdo.it/api/cotacao/cd_acao/' . $acao->cd_acao . "/15",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
    ));

    $response = curl_exec($curl);
    // fwrite($log, $response);
    // fwrite($log, "\n");
    curl_close($curl);
    $response = json_decode($response);
    $acao->deleteContentFechamento();
    foreach ($response as $ativos) {
        $acao->addToArray(number_format($ativos->vl_fechamento, 2, '.', ''));
    }
    fwrite($log, json_encode($acao));
    fwrite($log, "\n");

    if (count($acao->fechamentoQuinzenal) == 15 && $acao->curPrc != 0.0) {
        fwrite($dataLog, json_encode($acao));
        fwrite($dataLog, ",");
        fwrite($dataLog, "\n");
        post($acao);
    }
    sleep(2);
    fclose($dataLog);
}

function post($ativo)
{
    $teste = $GLOBALS["teste"];
    $conn = $GLOBALS["conn"];
    $aux = 0;
    $sql = '';
    foreach ($ativo->fechamentoQuinzenal as $row) {
        $sql .= ($aux == 0 ? $row : ", " . $row);
        $aux = 1;
    }
    $sql .= ");";

    $conn->begin_transaction();
    try {
        $conn->query("INSERT INTO Ativos(nm_empresa, cd_acao, curPrc, prcFlcn) values('$ativo->nm_empresa', '$ativo->cd_acao', '$ativo->curPrc', '$ativo->prcFlcn');");
        $conn->query("INSERT INTO VariacaoQuinzenal(ASSETS_ID, d1, d2, d3, d4, d5, d6, d7, d8, d9, d10, d11, d12, d13, d14, d15) values(LAST_INSERT_ID(), $sql");
        $conn->commit();
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        fwrite($teste, "erro");
        fwrite($teste, json_encode($exception->getMessage()));
    }
}
function deleteData(){
    $teste = $GLOBALS["teste"];
    $conn = $GLOBALS["conn"];
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM VariacaoQuinzenal");
        $conn->query("DELETE FROM Ativos");
        $conn->query("ALTER TABLE Ativos AUTO_INCREMENT = 0");
        $conn->commit();
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        fwrite($teste, "erro");
        fwrite($teste, json_encode($exception));
    }
}



//loop infinito que conta o tempo
while (true) {
    $time = date("H:i:s");
    //INSERINDO DADOS NO BANCO DIARIAMENTE ÀS 13:40
    if ($time == "14:20:00") {
        $conn = mysqli_connect($servername, $username, $password, $database);
        // Checkando conexão
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        deleteData();
        unlink("data.txt");
        listaAtivos();
        fwrite($dataLog, "]");
        fclose($dataLog);
    }else {
        fwrite($timeLog, $time);
        fwrite($timeLog, "\n");
    }
    sleep(1);
}

