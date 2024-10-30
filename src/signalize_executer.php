<?php
	// Inclusie van de StApp klasse
	include_once("stApp/StApp.php");
	$stApp = StApp::instance();
	
	// Ophalen van de bestandsnaam uit de GET-request en toevoegen van een extensie
	$fileName = "{$_GET["file"]}.signalize";
	
	// Controleren of het bestand bestaat; indien niet, 404 foutmelding sturen
	if (!file_exists($fileName)) {
		header("HTTP/1.1 404 Not Found");
		exit("404 Not Found");
	}
	
	// Controleren of het bestand een CSS-bestand is, zo ja, het juiste contenttype instellen
	if (str_contains($fileName, ".css.")) {
		header('Content-Type: text/css');
	}
	
	try {
		// Lexer en Parser initialiseren voor het verwerken van het bestand
		$lexer = new Services\Signalize\Lexer(file_get_contents($fileName));
		$parser = new Services\Signalize\Parser($lexer);
		
		// Parser gebruiken om de Abstract Syntax Tree (AST) te genereren
		$ast = $parser->parse($_GET ?? []);
		
		// Converteer de AST naar bytecode
		$bytecode = $parser->convertToBytecode($ast);
		
		// Voer de bytecode uit. Voer de inhoud van $_GET in als globale signalize variabelen
		$executer = new \Services\Signalize\Executer($bytecode);
		$executer->execute();
	} catch (Exception $exception) {
		// In geval van een uitzondering, 404 foutmelding sturen met de foutboodschap
		header("HTTP/1.1 404 Not Found");
		echo $exception->getMessage();
	}