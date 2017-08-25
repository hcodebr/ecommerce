<?php 

use \Hcode\Page;
use \Hcode\Model\Product;
use \Hcode\Model\Category;
use \Hcode\Model\Cart;
use \Hcode\Model\User;

$app->get('/', function() {
    
	$products = Product::listAll();

	$page = new Page();

	$page->setTpl("index", [
		'products'=>Product::checkList($products)
	]);

});

$app->get("/categories/:idcategory", function($idcategory){

	$page = (isset($_GET['page'])) ? (int)$_GET['page'] : 1;

	$category = new Category();

	$category->get((int)$idcategory);

	$pagination = $category->getProductsPage($page);

	$pages = [];

	for ($i=1; $i <= $pagination['pages']; $i++) { 
		array_push($pages, [
			'link'=>'/categories/'.$category->getidcategory().'?page='.$i,
			'page'=>$i
		]);
	}

	$page = new Page();

	$page->setTpl("category", [
		'category'=>$category->getValues(),
		'products'=>$pagination["data"],
		'pages'=>$pages
	]);

});

$app->get("/products/:desurl", function($desurl){

	$product = new Product();

	$product->getFromURL($desurl);

	$page = new Page();

	$page->setTpl("product-detail", [
		'product'=>$product->getValues(),
		'categories'=>$product->getCategories()
	]);

});

$app->get("/cart", function(){

	$cart = Cart::getFromSession();

	$page = new Page();

	$page->setTpl("cart", [
		'cart'=>$cart->getValues(),
		'products'=>$cart->getProducts(),
		'error'=>$cart->getCartError()
	]);

});

$app->get("/cart/:idproduct/add", function($idproduct){

	$product = new Product();

	$product->get((int)$idproduct);

	$cart = Cart::getFromSession();

	$qtd = (isset($_GET['qtd'])) ? (int)$_GET['qtd'] : 1;

	for ($i = 0; $i < $qtd; $i++) {
		
		$cart->addProduct($product);

	}

	header("Location: /cart");
	exit;

});

$app->get("/cart/:idproduct/minus", function($idproduct){

	$product = new Product();

	$product->get((int)$idproduct);

	$cart = Cart::getFromSession();

	$cart->removeProduct($product);

	header("Location: /cart");
	exit;

});

$app->get("/cart/:idproduct/remove", function($idproduct){

	$product = new Product();

	$product->get((int)$idproduct);

	$cart = Cart::getFromSession();

	$cart->removeProduct($product, true);

	header("Location: /cart");
	exit;

});

$app->post("/cart/freight", function(){

	$nrzipcode = str_replace("-", "", $_POST['zipcode']);

	$cart = Cart::getFromSession();

	$cart->setFreight($nrzipcode);

	header("Location: /cart");
	exit;

});

$app->get("/checkout", function(){

	User::verifyLogin(false);

	$cart = Cart::getFromSession();

	$page = new Page();

	$page->setTpl("checkout", [
		'cart'=>$cart->getValues(),
		'address'=>[]
	]);

});

$app->get("/login", function(){

	$page = new Page();

	$page->setTpl("login", [
		'loginError'=>User::getError(),
		'registerError'=>User::getErrorRegister(),
		'postValues'=>(isset($_SESSION['postValues'])) ? $_SESSION['postValues'] : ['name'=>'', 'email'=>'', 'phone'=>'']
	]);

});

$app->post("/login", function(){

	try {

		User::login($_POST["login"], $_POST["password"]);

	} catch (Exception $e) {

		User::setError($e->getMessage());

	}

	header("Location: /checkout");
	exit;

});

$app->get("/logout", function(){

	User::logout();

	header("Location: /login");
	exit;

});

$app->post("/register", function(){

	$_SESSION['postValues'] = $_POST;
	$_SESSION['postValues']['password'] = '';

	if (!isset($_POST['name']) || $_POST['name']=='') {
		User::setErrorRegister("Preencha o nome completo.");
		header('Location: /login');
		exit;
	}

	if (!isset($_POST['email']) || $_POST['email']=='') {
		User::setErrorRegister("Preencha o e-mail.");
		header('Location: /login');
		exit;
	}

	if (!isset($_POST['password']) || $_POST['password']=='') {
		User::setErrorRegister("Preencha a senha.");
		header('Location: /login');
		exit;
	}

	if (User::checkLoginExist($_POST['email'])) {
		User::setErrorRegister("Este usuário já está cadastrado. Use a opção esqueci a senha.");
		header('Location: /login');
		exit;
	}

	$user = new User();

	$user->setData([
		'desperson'=>utf8_decode($_POST['name']),
		'deslogin'=>$_POST['email'],
		'desemail'=>$_POST['email'],
		'nrphone'=>$_POST['phone'],
		'despassword'=>User::getPassswordHash($_POST['password']),
		'inadmin'=>0
	]);
	
	$user->save();

	try {

		User::login($_POST["email"], $_POST["password"]);

	} catch (Exception $e) {

		User::setError($e->getMessage());
		header('Location: /login');
		exit;

	}

	$_SESSION['postValues'] = ['name'=>'', 'email'=>'', 'phone'=>''];

	header('Location: /checkout');
	exit;

});

 ?>