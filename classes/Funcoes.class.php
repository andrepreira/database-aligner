<?php
/**
 * Classe responsável por executar a comparação das estruturadas dos bancos de dados
 *
 * @package     Classe
 * @subpackage  Funcoes
 * @name        ClasseFuncoes
 * @version     1.0
 * @copyright   Webart
 * @author      William Costa
 *
 */

class Funcoes{

  //DADOS DOS BANCOS A E B
  private $bancoA = null;
  private $bancoB = null;

  //TODAS AS TABELAS DOS BANCOS A E B
  private $tabelasA = null;
  private $tabelasB = null;

  //TODAS AS COLUNAS DOS BANCOS A E B
  private $colunasA = null;
  private $colunasB = null;

  //ANALISE DE TABELAS ÚNICAS DOS BANCOS A E B
  private $analiseTabelasAB = null;
  private $analiseTabelasBA = null;

  //ANALISE COMPLETA DE COLUNAS ÚNICAS E ALTERADAS NOS BANCOS A E B
  private $analiseColunasAB = array();

  //VARIÁVEIS QUE RECEBE O ESTADO DE IGUALDADE DAS ESTRUTURAS DOS BANCOS
  private $resultadoBancoA  = true;
  private $resultadoBancoB  = true;
  private $resultadoColunas = true;

  //MÉTODO SET DAS INFORMAÇÕES DO BANCO A ENVIADAS POR POST
  public function setBancoA($bd){
    $this->bancoA = $bd;
  }

  //MÉTODO SET DAS INFORMAÇÕES DO BANCO B ENVIADAS POR POST
  public function setBancoB($bd){
    $this->bancoB = $bd;
  }

  //MÉTODO GET BANCO A RETORNA UM OBJETO CARREGADO COM AS INFORMAÇÕES DO BANCO A
  public function getBancoA(){
    global $bdconfig;
    $bdconfig = $this->bancoA;
    return new Banco;
  }

  //MÉTODO GET BANCO B RETORNA UM OBJETO CARREGADO COM AS INFORMAÇÕES DO BANCO B
  public function getBancoB(){
    global $bdconfig;
    $bdconfig = $this->bancoB;
    return new Banco;
  }

  //MÉTODO GET ANALISE EXECUTA OS MÉTODOS DE ANÁLISES ESTRUTURAIS DE TABELAS E COLUNAS E OBTEM O RETORNO DA ANALISE
  public function getAnalise(){
    $this->getAnaliseTabelas();
    $this->getAnaliseColunas();
    return $this->getRetorno();
  }
  
  //MÉTODO DE ANÁLISE DE TABELASS
  public function getAnaliseTabelas(){

      //OBTEM OS OBJETOS
      $obA = $this->getBancoA();
      $obB = $this->getBancoB();

      //SQL PARA MOSTRAR AS TABELAS
      $sql = 'SHOW TABLES;';

      //EXECUÇÃO DAS QUERIES
      $resA = $obA->execSQL($sql);

      //AUXILIARES
      $auxiliarA   = array();
      $constraintsA = array();

      //NOME DO CAMPO RETORNADO NA SQL A
      $data = 'Tables_in_'.$this->bancoA['banco'];

      //LAÇO PARA ATRIBUIR OS CREATE TABLE DAS TABELAS DO BANCO A AO AUXILIAR A
      while($lineA = $resA->fetchObject()){
        $auxiliarA[$lineA->$data] = $obA->execSQL("SHOW CREATE TABLE " . $lineA->$data)->fetch(PDO::FETCH_ASSOC);
      }      
      // REMOVER DEPOIS DE TESTES
      ini_set('display_errors',true);
      error_reporting(E_ALL);
      // REMOVER DEPOIS DE TESTES
      foreach($auxiliarA as $key=>$value){
        $query = str_replace("CREATE TABLE","CREATE TABLE IF NOT EXISTS",$value["Create Table"]);
        // FILTRAGEM DE ESPAÇOS DA QUERY 
        $query = str_replace("\n","",$query);
        $query = str_replace("/ {2,}/","",$query);
        // LOCALIZAÇÃO DO INÍCIO E FINAL DAS CONSTRAINTS SE EXISTIREM
        $constraintPosition = strpos($query,"CONSTRAINT");
        $enginePosition     = strpos($query,") ENGINE");
        
        if($constraintPosition){

          // EXTRAÇÃO DAS CONSTRAINTS
          $constraints = (substr($query,$constraintPosition,$enginePosition-$constraintPosition));
          // REMOÇÃO DAS CONSTRAINTS DA QUERY
          $query = str_replace($constraints,"",$query);
          // LOCALIZAÇÃO RELATIVA DA ALTERAÇÃO ANTERIOR PARA REMOVER , EXCEDENTE
          $enginePosition = strpos($query,") ENGINE");
          // REMOÇÃO DA VíRGULA EXCEDENTE
          $lastCommaPosition = strrpos($query,strrchr($query,','));
          $query[$lastCommaPosition] = " ";

          $constraints = explode(",",$constraints);
          foreach($constraints as $key=>$constraint){
            $constraintName   = $this->getConstraintName($constraint);
            $dropConstraint   = "ALTER TABLE " . $value["Table"] . " DROP CONSTRAINT IF EXISTS " . $constraintName . ";";
            $createConstraint = "ALTER TABLE " . $value["Table"] . " ADD " . $constraint;
            $constraintsA[]    = $dropConstraint . $createConstraint;
          }
        }
        echo "Criando a tabela <b>" . $value["Table"] . "</b><br>";
        $obB->execSQL($query);
      }     
      
      echo '<pre>';print_r("Tabelas Criadas. Criando Constraints");echo'</pre>';
      foreach($constraintsA as $key=>$queryConstraint){
        echo "Criando a constraint <b>" . $this->getConstraintName($queryConstraint) . "</b><br>";
        $obB->execSQL($queryConstraint);
      }
      
      echo '<pre>';print_r("Constraints Criadas.");echo'</pre>'; exit;
  }
  //MÉTODO QUE EXTRAI O NOME DA CONSTRAINT SQL DE UMA QUERY
  private function getConstraintName($query){
    $posicaoUltimaVirgula = strrpos($query,"`");
    $constraintName = substr($query,strpos($query,"`")+1);
    $posicaoUltimaVirgula = strpos($constraintName,strstr($constraintName,"`"));
    $constraintName = substr($constraintName,0,$posicaoUltimaVirgula);
    return $constraintName;
  }

  //MÉTODO DE ANÁLISE ESTRUTURAL DAS COLUNAS DE CADA TABELA DOS BANCOS
  public function getAnaliseColunas(){

    //OBTEM OS OBJETOS DOS BANCOS A E B
    $obA = $this->getBancoA();
    $obB = $this->getBancoB();

    //SQL PARA MOSTRAR AS COLUNAS
    $sql = 'SHOW COLUMNS FROM ';

    //OBTENDO AS TABELAS SALVAS NAS VARIÁVEIS PELO MÉTODO ANTERIOR
    $tabelasA = $this->tabelasA;
    $tabelasB = $this->tabelasB;

    //OBTEM SOMENTE AS TABELAS QUE ESTIVEREM NA INTERSECÇÃO DOS DOIS BANCOS
    $tabelasAB = array_intersect($tabelasA,$tabelasB);


    //PRIMEIRO LAÇO PARA ANDAR PELAS TABELAS
    foreach($tabelasAB as $key=>$value){

      //AUXILIARES
      $auxA = array();
      $auxB = array();
      $altCamposAB = array();
      $mudancas = false;

      //EXECUÇÃO DAS QUERIES PARA OBTER OS CAMPOS
      $resA = $obA->execSQL($sql.$value);
      $resB = $obB->execSQL($sql.$value);

      //SEGUNDO LAÇO PARA ATRIBUIR OS CAMPOS DA TABELA DO BANCO A AO AUXILIAR A
      while($lineA = $resA->fetch(PDO::FETCH_ASSOC)){
        $auxA[$lineA['Field']] = $lineA;
      }

      //TERCEIRO LAÇO PARA ATRIBUIR OS CAMPOS DA TABELA DO BANCO B AO AUXILIAR B
      while($lineB = $resB->fetch(PDO::FETCH_ASSOC)){
        $auxB[$lineB['Field']] = $lineB;
      }

      //OBTENDO OS CAMPOS ÚNICOS
      $difCamposAB = array_diff_assoc($auxA,$auxB);
      $difCamposBA = array_diff_assoc($auxB,$auxA);

      //OBTENDO OS CAMPOS QUE ESTIVEREM NA INTERSECÇÃO DOS DOIS BANCOS
      $camposAB = array_intersect_assoc($auxA,$auxB);

      //VALORES DEFAULT TIMESTAMP
      $valoresDefaultTimestamp = ['CURRENT_TIMESTAMP','current_timestamp()'];

      //QUARTO LAÇO PARA DESCOBRIR CAMPOS QUE ESTIVEREM DIFERENTES NOS DOIS BANCOS
      foreach($camposAB as $key2=>$value2){
        if(in_array($auxA[$key2]['Default'],$valoresDefaultTimestamp) and in_array($auxB[$key2]['Default'],$valoresDefaultTimestamp)) continue;
        if($auxA[$key2] != $auxB[$key2]){
          $altCamposAB[$key2]['a'] = $auxA[$key2];
          $altCamposAB[$key2]['b'] = $auxB[$key2];
          $mudancas = true;
        }
      }
      //ATRIBUIÇÃO DAS VARIÁVEIS DA CLASSE CASO HAJA ALTERAÇÕES
      if($mudancas or !empty($difCamposAB) or !empty($difCamposBA)){
        $this->analiseColunasAB[$value]['a']   = $difCamposAB;
        $this->analiseColunasAB[$value]['b']   = $difCamposBA;
        $this->analiseColunasAB[$value]['dif'] = $altCamposAB;
        $this->resultadoColunas = false;
      }
    }
  }

  //MÉTODO PARA RETORNAR UM ARRAY CONTENDO AS INFORMAÇÕES DA ANÁLISE PARA SEREM EXIBIDAS NO ARQUIVO DE RESULTADO
  public function getRetorno(){
    $retorno = array();
    if(!$this->resultadoBancoA) $retorno['analiseTabelasAB'] = $this->analiseTabelasAB;
    else{
      $retorno['analiseTabelasAB'][] = 'Não há tabelas únicas no Banco A';
      $bancoA = true;
    }

    if(!$this->resultadoBancoB) $retorno['analiseTabelasBA'] = $this->analiseTabelasBA;
    else{
      $retorno['analiseTabelasBA'][] = 'Não há tabelas únicas no Banco B';
      $bancoB = true;
    }

    if(!$this->resultadoColunas) $retorno['analiseColunasAB'] = $this->analiseColunasAB;
    else{
      $colunas = true;
    }

    if($bancoA AND $bancoB AND $colunas){
      $retorno['resultado'] = 'Bancos estruturalmente iguais.';
    }else{
      $retorno['resultado'] = 'Os bancos apresentam diferenças estruturais.';
    }

    return $retorno;
  }
  public static function getArquivo(){
    $fp = fopen("config/favorite.txt", 'r');
    $data['host']     = str_replace("\n","",fgets($fp)); // HOST
    $data['port']     = str_replace("\n","",fgets($fp)); // PORT
    $data['user']     = str_replace("\n","",fgets($fp)); // USER
    $data['password'] = str_replace("\n","",fgets($fp)); // PASSWORD
    $data['database'] = str_replace("\n","",fgets($fp)); // DATABASE
    fclose($fp); 
    echo json_encode($data);
  }
  public static function setArquivo($host, $port, $user, $password, $database){
    try{
      $fp = fopen("config/favorite.txt", 'w');
      fwrite($fp, $host     . "\n"); // HOST
      fwrite($fp, $port     . "\n"); // PORT
      fwrite($fp, $user     . "\n"); // USER
      fwrite($fp, $password . "\n"); // PASSWORD
      fwrite($fp, $database . "\n"); // DATABASE
      fclose($fp);    
      http_response_code(200);
    }catch(Exception $e){
      http_response_code(500);
    }
    
  }

}
