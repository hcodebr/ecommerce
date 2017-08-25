<?php 

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Mailer;
use \Hcode\Model\User;

class Cart extends Model {

	const SESSION = "Cart";
	const ERROR = "CartError";

	public static function getFromSession()
	{

		$cart = new Cart();

		if (isset($_SESSION[Cart::SESSION]) && (int)$_SESSION[Cart::SESSION]['idcart'] > 0) {

			$cart->get((int)$_SESSION[Cart::SESSION]['idcart']);

		} else {

			$cart->getFromSessionID();

			if (!(int)$cart->getidcart() > 0) {

				$data = [
					'dessessionid'=>session_id()
				];

				if (User::checkLogin(false)) {

					$user = User::getFromSession();
					
					$data['iduser'] = $user->getiduser();	

				}

				$cart->setData($data);

				$cart->save();

				$cart->setToSession();


			}

		}

		return $cart;

	}

	public function setToSession()
	{

		$_SESSION[Cart::SESSION] = $this->getValues();

	}

	public function getFromSessionID()
	{

		$sql = new Sql();

		$results = $sql->select("SELECT * FROM tb_carts WHERE dessessionid = :dessessionid", [
			':dessessionid'=>session_id()
		]);

		if (count($results) > 0) {

			$this->setData($results[0]);

		}

	}	

	public function get(int $idcart)
	{

		$sql = new Sql();

		$results = $sql->select("SELECT * FROM tb_carts WHERE idcart = :idcart", [
			':idcart'=>$idcart
		]);

		if (count($results) > 0) {

			$this->setData($results[0]);

		}

	}

	public function save()
	{

		$sql = new Sql();

		$results = $sql->select("CALL sp_carts_save(:idcart, :dessessionid, :iduser, :deszipcode, :vlfreight, :nrdays)", [
			':idcart'=>$this->getidcart(),
			':dessessionid'=>$this->getdessessionid(),
			':iduser'=>$this->getiduser(),
			':deszipcode'=>$this->getdeszipcode(),
			':vlfreight'=>$this->getvlfreight(),
			':nrdays'=>$this->getnrdays()
		]);

		$this->setData($results[0]);

	}

	public function addProduct(Product $product)
	{

		$sql = new Sql();

		$sql->query("INSERT INTO tb_cartsproducts (idcart, idproduct) VALUES(:idcart, :idproduct)", [
			':idcart'=>$this->getidcart(),
			':idproduct'=>$product->getidproduct()
		]);

	}

	public function removeProduct(Product $product, $all = false)
	{

		$sql = new Sql();

		if ($all) {

			$sql->query("UPDATE tb_cartsproducts SET dtremoved = NOW() WHERE idcart = :idcart AND idproduct = :idproduct AND dtremoved IS NULL", [
				':idcart'=>$this->getidcart(),
				':idproduct'=>$product->getidproduct()
			]);

		} else {

			$sql->query("UPDATE tb_cartsproducts SET dtremoved = NOW() WHERE idcart = :idcart AND idproduct = :idproduct AND dtremoved IS NULL LIMIT 1", [
				':idcart'=>$this->getidcart(),
				':idproduct'=>$product->getidproduct()
			]);

		}

	}

	public function getProducts()
	{

		$sql = new Sql();

		$rows = $sql->select("
			SELECT b.idproduct, b.desproduct , b.vlprice, b.vlwidth, b.vlheight, b.vllength, b.vlweight, b.desurl, COUNT(*) AS nrqtd, SUM(b.vlprice) AS vltotal 
			FROM tb_cartsproducts a 
			INNER JOIN tb_products b ON a.idproduct = b.idproduct 
			WHERE a.idcart = :idcart AND a.dtremoved IS NULL 
			GROUP BY b.idproduct, b.desproduct , b.vlprice, b.vlwidth, b.vlheight, b.vllength, b.vlweight, b.desurl 
			ORDER BY b.desproduct
		", [
			':idcart'=>$this->getidcart()
		]);

		return Product::checkList($rows);

	}

	public function getProductsTotals()
	{

		$sql = new Sql();

		$results = $sql->select("
			SELECT COUNT(*) AS nrqtd, SUM(b.vlprice) AS vlprice, SUM(b.vlwidth) AS vlwidth, SUM(b.vlheight) AS vlheight, SUM(b.vllength) AS vllength, SUM(b.vlweight) AS vlweight
			FROM tb_cartsproducts a
			INNER JOIN tb_products b ON a.idproduct = b.idproduct
			WHERE a.idcart = :idcart AND a.dtremoved IS NULL
		", [
			':idcart'=>$this->getidcart()
		]);

		if (count($results) > 0) {

			return $results[0];

		} else {

			return [
				'vlprice'=>0,
				'vlweight'=>0,
				'vlheight'=>0,
				'vllength'=>0,
				'vlweight'=>0
			];

		}

	}

	public function getCalculateTotal()
	{

		$totals = $this->getProductsTotals();

		$this->updateFreight();

		$this->setvlsubtotal($totals['vlprice']);
		$this->setvltotal($totals['vlprice'] + $this->getvlfreight());

	}

	public function getValues()
	{

		$this->getCalculateTotal();

		$values = parent::getValues();

		return $values;

	}

	public function setFreight($zipcode)
	{

		$totals = $this->getProductsTotals();

		if ($totals['nrqtd'] === 0) {

			$this->setvlfreight(NULL);
			$this->setnrdays(NULL);
			$this->setdeszipcode(NULL);

			$this->save();

			return false;

		}

		$vllength = ($totals['vllength'] < 16) ? 16 : $totals['vllength'];

		$qs = http_build_query([
			"nCdEmpresa"=>"",
			"sDsSenha"=>"",
			"nCdServico"=>"40010",
			"sCepOrigem"=>"09853120",
			"sCepDestino"=>$zipcode,
			"nVlPeso"=>$totals['vlweight'],
			"nCdFormato"=>"1",
			"nVlComprimento"=>$vllength,
			"nVlAltura"=>$totals['vlheight'],
			"nVlLargura"=>$totals['vlwidth'],
			"nVlDiametro"=>"0",
			"sCdMaoPropria"=>"S",
			"nVlValorDeclarado"=>$totals['vlprice'],
			"sCdAvisoRecebimento"=>"S"
		]);

		$url = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.asmx/CalcPrecoPrazo?".$qs;

		$xml = simplexml_load_file($url);
		$response = (array)$xml->Servicos->cServico;

		if ($response['MsgErro'] != '') {

			$this->setCartError($response['MsgErro']);

		}

		$this->setvlfreight(Cart::valueToDecimal($response['Valor']));
		$this->setnrdays($response['PrazoEntrega']);
		$this->setdeszipcode($zipcode);

		$this->save();

		return true;

	}

	public static function valueToDecimal($value)
	{

		$value = str_replace('.', '', $value);
		return str_replace(',', '.', $value);

	}

	public function setCartError($msg)
	{

		$_SESSION[Cart::ERROR] = $msg;

	}

	public function getCartError()
	{

		$msg = (isset($_SESSION[Cart::ERROR]) && $_SESSION[Cart::ERROR]) ? $_SESSION[Cart::ERROR] : '';

		$this->clearCartError();

		return $msg;

	}

	public function clearCartError()
	{

		$_SESSION[Cart::ERROR] = NULL;

	}

	public function updateFreight()
	{

		if ($this->getdeszipcode()) {

			$this->setFreight($this->getdeszipcode());

		}

	}

}

 ?>