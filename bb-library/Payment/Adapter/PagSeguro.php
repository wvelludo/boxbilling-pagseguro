<?php

/**
 * Boxbilling pagamento via Pagseguro
 *
 * Gera transacao via API e processa o pagamento
 *
 * @package    Pagseguro
 * @author     Weverton Velludo <wv@brasilnetwork.com.br>
 * @copyright  Copyright (c) Weverton Velludo 2017
 * @license    Apache 2.0
 * @version    $Id$
 * @link       http://www.github.com/wvelludo/boxbilling-pagseguro
 */

class Payment_Adapter_Pagseguro extends Payment_AdapterAbstract
{
    /**
     * @var Box_Di
     */
    protected $di;

    /**
     * @param Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }
	
    public function init()
    {
        if(!$this->getParam('email')) {
            throw new Payment_Exception('Para que este modulo funciona corretamente o e-mail deve ser informado em "Configuration -> Payments".');
        }
        
        if (!$this->getParam('token')) {
        	throw new Payment_Exception('Para que este modulo funciona corretamente o token deve ser informado em "Configuration -> Payments".');
        }
    }
    
    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   	=>  true,
            'supports_subscriptions'     	=>  false,
            'description'    				=>  'Os clientes serao redirecionados para o Pagseguro para fazer o pagamento.',
            'form'  => array(
                'email' => array('text', array(
                            'label' => 'Pagseguro E-mail', 
                            'description' => 'Seu e-mail Pagseguro',
                            'validators'=>array('EmailAddress'),
                    ),
                 ),
                 'token' => array('password', array(
                 			'label' => 'Token',
                 			'description' => 'Seu Token de seguranca gerado via pagseguro',
                 			'validators' => array('notempty'),
                 	),
                 ),
                 'campodocumento' => array('text', array(
                 			'label' => 'Token',
                 			'description' => 'Campo personalizado em que consta o CPF/CNPJ',
                 			'validators' => array('notempty'),
                 	),
                 ),
                 'sandbox' => array('select', array(
                             'multiOptions' => array(
                                 '1' => 'Sim',
                                 '0' => 'Nao'
                             ),
                             'label' => 'Sandbox - Ambiente de Testes',
                     ),
                  ),
            ),
        );
    }
    
    /**
     * Return payment gateway type
     * @return string
     */
    public function getType()
    {
        return Payment_AdapterAbstract::TYPE_FORM;
    }
    
    /**
     * Return payment gateway type
     * @return string
     */
    public function getServiceUrl()
    {
        if($this->getParam('sandbox')) {
			return 'https://sandbox.pagseguro.uol.com.br/v2/checkout/payment.html?code='.$this->transacao;
        }
		return 'https://pagseguro.uol.com.br/v2/checkout/payment.html?code=' . $this->transacao;
    }

	public function singlePayment(Payment_Invoice $invoice) 
	{
		$c 			= $invoice->getBuyer();
        $rows	 	= $this->di['db']->getAll('SELECT * FROM client WHERE email = :email AND role = :role', array(':email' => $c->getEmail(),':role' => 'client'));
        $cliente 	= $rows[0];
		$endereco	= explode(",",$cliente["address_1"]);
		$telefone	= explode(" ",$c->getPhone());
		
		$xml 		= new ExSimpleXMLElement('<checkout></checkout>');
		$sender		= $xml->addChild('sender');
					  $sender->addChild('name',$c->getFirstName() . ' ' . $c->getLastName());
					  $sender->addChild('email',$c->getEmail());
		$phone 		= $sender->addChild('phone');
					  $phone->addChild('areaCode',$telefone[0]);
					  $phone->addChild('number',$telefone[1]);
					  $sender->addChild('ip',$_SERVER["REMOTE_ADDR"]);
		$documents	= $sender->addChild('documents');
		$document	= $documents->addChild('document');
		
		if ($cliente["type"] == "individual") {
		  			  $document->addChild('type','CPF');
		} else {
		  			  $document->addChild('type','CNPJ');
		}
		  			  $document->addChild('value',$cliente[$this->getParam('campodocumento')]);
					  $xml->addChild('currency',$invoice->getCurrency());
		$items		= $xml->addChild('items');
		
		$i = 1;
		foreach ($invoice->getItems() as $item) {
			$variavel   = "item".$i;
			$$variavel	= $items->addChild('item');
						  $$variavel->addChild('id',$item->getId());
						  $$variavel->addChild('description',$item->getTitle());
						  $$variavel->addChild('quantity',$item->getQuantity());
						  $$variavel->addChild('amount',number_format($item->getPrice() + $item->getTax(), 2, '.', ''));
		}
					  $xml->addChildCData('redirectURL',$this->getParam('return_url'));
					  $xml->addChild('reference',$invoice->getId());
		$shipping	= $xml->addChild('shipping');
		$address	= $shipping->addChild('address');
					  $address->addChildCData('street',$endereco[0]);
					  $address->addChild('number',trim($endereco[1]));
					  $address->addChild('district',$cliente["address_2"]);
					  $address->addChild('city',$c->getCity());
					  $address->addChild('state',$c->getState());
					  $address->addChild('country','BRA');
					  $address->addChild('postalCode',$c->getZip());
					  $shipping->addChild('type','3');
		$receiver	= $xml->addChild('receiver');
					  $receiver->AddChild('email',$this->getParam('email'));
			  
		$dados = $xml->asXML();

		if($this->getParam('sandbox')) {
			$url  = "https://ws.sandbox.pagseguro.uol.com.br/v2/checkout/?email=" . $this->getParam('email') . "&token=" . $this->getParam('token');
		} else {
			$url  = "https://ws.pagseguro.uol.com.br/v2/checkout/?email=" . $this->getParam('email') . "&token=" . $this->getParam('token');
		}
		
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, Array('Content-Type: application/xml; charset=UTF-8'));
		curl_setopt($curl, CURLOPT_POSTFIELDS, $dados);
		$retorno = curl_exec($curl);
		curl_close($curl);
		
		if($retorno == 'Unauthorized') {
			throw new Payment_Exception('Erro ao gerar transacao');	
		} else {
			$xml= simplexml_load_string($retorno);
			if(count($xml->error) > 0){
			    throw new Payment_Exception('Erro ao processar transacao: ' . $xml->error->message . $cliente["address_1"]);	
			} else {
				$this->transacao = $xml->code;
			}	
		}
	}


	public function recurrentPayment(Payment_Invoice $invoice) 
	{
		throw new Payment_Exception('Not implemented yet');	
	}

	public function pagseguroStatus($status) {
		switch ($status) {
		    case 1: return "Aguardando pagamento"; break;
			case 2: return "Em análise"; break;
			case 3: return "Paga"; break;
			case 4: return "Paga"; break; // Disponivel para saque
			case 5: return "Em disputa"; break;
			case 6: return "Devolvida"; break;
			case 7: return "Cancelada"; break;
			case 8: return "Debitado"; break;
			case 9: return "Retenção temporária"; break;
		}
	}

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {

		$tipo 		= $data['post']['notificationType'];
		$transacao 	= $data['post']['notificationCode'];
		
		if ($tipo == "transaction") {
			
			if($this->getParam('sandbox')) {
				$url  = "https://ws.sandbox.pagseguro.uol.com.br/v3/transactions/notifications/" . $transacao . "?email=" . $this->getParam('email') . "&token=" . $this->getParam('token');
			} else {
				$url  = "https://ws.pagseguro.uol.com.br/v3/transactions/notifications/" . $transacao . "?email=" . $this->getParam('email') . "&token=" . $this->getParam('token');
			}
		
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER, Array('Content-Type: application/xml; charset=UTF-8'));
			$retorno = curl_exec($curl);
			curl_close($curl);
			
			$xml = simplexml_load_string($retorno);
			
			$data			= $xml->date;
			$transacao		= $xml->code;
			$invoice 		= $xml->reference;
			$status  		= $xml->status;
			$statustxt		= $this->pagseguroStatus($status);
			$ultimoevento 	= $xml->lastEventDate;
			$tipopagamento	= $xml->paymentMethod->type;
			$linkpagamento	= $xml->paymentLink;
			$valor			= $xml->grossAmount;
			
            if($status == "3" || $status == "4") {
				
				$tx = $api_admin->invoice_transaction_get(array('id'=>$id));
		
		        if(!$tx['invoice_id']) {
		            $api_admin->invoice_transaction_update(array('id'=>$id,'invoice_id'=>(int)$invoice,'txn_id'=>(string)$transacao,'txn_status'=>(string)$this->pagseguroStatus($status),'amount'=>(float)$valor,'currency'=>'BRL',));
			        
					$invoice = $api_admin->invoice_get(array('id'=>(int)$invoice));
			        $client_id = $invoice['client']['id'];
			
	                $bd = array(
	                    'id'            =>  $client_id,
	                    'amount'        =>  (float) $valor,
	                    'description'   =>  'Pagseguro transaction '.(string)$transacao,
	                    'type'          =>  'Pagseguro',
	                    'rel_id'        =>  (int)$invoice,
	                );
	                $api_admin->client_balance_add_funds($bd);
              		$api_admin->invoice_batch_pay_with_credits(array('client_id'=>$client_id));
				}
            }
		} else {
			throw new Payment_Exception('Nao Permitido');	
		}
		
    }

	public function getTransaction($data, Payment_Invoice $invoice) 
	{
		throw new Payment_Exception('Not implemented yet');	
	}

    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
		return ($ipn['ap_securitycode'] == $this->getParam('securityCode'));
    }
}

/**
 *
 * Extension for SimpleXMLElement
 * @author Alexandre FERAUD
 *
 */

class ExSimpleXMLElement extends SimpleXMLElement 
{ 
    /** 
     * Add CDATA text in a node 
     * @param string $cdata_text The CDATA value  to add 
     */ 
  private function addCData($cdata_text) 
  { 
   $node= dom_import_simplexml($this); 
   $no = $node->ownerDocument; 
   $node->appendChild($no->createCDATASection($cdata_text)); 
  } 

  /** 
   * Create a child with CDATA value 
   * @param string $name The name of the child element to add. 
   * @param string $cdata_text The CDATA value of the child element. 
   */ 
    public function addChildCData($name,$cdata_text) 
    { 
        $child = $this->addChild($name); 
        $child->addCData($cdata_text); 
    } 

    /** 
     * Add SimpleXMLElement code into a SimpleXMLElement 
     * @param SimpleXMLElement $append 
     */ 
    public function appendXML($append) 
    { 
        if ($append) { 
            if (strlen(trim((string) $append))==0) { 
                $xml = $this->addChild($append->getName()); 
                foreach($append->children() as $child) { 
                    $xml->appendXML($child); 
                } 
            } else { 
                $xml = $this->addChild($append->getName(), (string) $append); 
            } 
            foreach($append->attributes() as $n => $v) { 
                $xml->addAttribute($n, $v); 
            } 
        } 
    } 
} 
