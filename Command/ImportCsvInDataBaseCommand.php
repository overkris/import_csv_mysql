<?php
namespace Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Input\InputArgument;

class ImportCsvInDataBaseCommand extends Command
{
	const NAME_FILE_DATA = "data.sql";
	const NAME_FILE_TABLE = 'create_table.sql';
	const NAME_TABLE = 'data_import';
	
	/**
	 * Config de la commande
	 * {@inheritDoc}
	 * @see \Symfony\Component\Console\Command\Command::configure()
	 */
    protected function configure()
    {
    	$this
	    	// the name of the command (the part after "bin/console")
	    	->setName('importcsv')
	    	->setDescription('Import des fichier CSV')
	    	->addArgument('path_rep', InputArgument::REQUIRED, 'Le rep du dossier à importer');
    	
    }

    /**
     * Exec de la commande
     * {@inheritDoc}
     * @see \Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	// @TODO A mettre en var
    	$iLigneNameColonne = 1;
    	$iLigneStartData = 2;
    	
    	
    	// Get du path du rep
    	$pathRep = $input->getArgument('path_rep');
    	$output->writeln("Chargement des fichiers du dossier".$pathRep);
    	$output->writeln("");
    	
    	// Init du finder
    	$finder = new Finder();
    	$finder->files()->in($pathRep)->name('*.CSV')->name('*.csv');
    	
    	// Get de la liste des fichiers
    	$output->writeln("Liste des fichiers fichier CSV pour ".$pathRep);
    	$aListeFiles = array();
    	foreach ($finder as $file) {
    		// Dump the absolute path
    		$output->writeln("  -  ".$file->getRelativePathname());
    		$aListeFiles[] = array("name" => $file->getRelativePathname(), "path" =>$file->getRealPath());
    	}
    	
    	$output->writeln("");

    	// Création du fichier des données
    	$fp = fopen(self::NAME_FILE_DATA, 'w');
    	
    	// Traitement de chaque fichier CSV
    	$aDimensionVar = $aNameColonne = array();
    	$bInitDim = true;
    	$sChaineColonne = "";
    	
    	foreach ($aListeFiles as $sValuePathFiles) {
    		$output->writeln("Construction des données pour le fichier ".$sValuePathFiles["name"]." ...");
    		
    		// Ouverture du fichier CSV en cours et parse des lignes
			$sTexteWrite = "";
			$iNbLigneInsert = $iNumLigne = 0;
    		if (($handle = fopen($sValuePathFiles["path"], "r")) !== FALSE) {
    			// Traitement ligne à ligne
    			while (($data = fgetcsv($handle, null, ";")) !== FALSE) {
    				// Compteur de ligne
    				$iNumLigne++;
    				// Si on est sur des lignes de données
    				if ($iNumLigne >= $iLigneStartData) {
    					// Si on arrive à 100 on recommence un groupe de données
    					if ($iNbLigneInsert >= 100) {
    						// On efface les derniers caractères
    						$sTexteWrite = substr($sTexteWrite, 0, -2);
    						$sTexteWrite .= ";\n";
    						$iNbLigneInsert = 0;
    					}
    					
    					// Création de l'en-tête d'une ligne de données
    					if ($iNbLigneInsert == 0) {
	    					$sTexteWrite .= "INSERT INTO ".self::NAME_TABLE." ($sChaineColonne) VALUES\n";
    					}

    					// Construction de la ligne de données
    					$iFirstCol = true;
    					$sTexteWrite .= "  (";
    					foreach ($data as $key => $value) {
    						// Si on est pas sur la première colonne on ajoute une virgule
    						if (!$iFirstCol) {
    							$sTexteWrite .= ",";
    						}
    						$iFirstCol = false;
    						
    						// Si la taille de la données est supérieur à celle du tableau on met à jour le tableau
    						if (strlen($value) > $aDimensionVar[$key]) {
    							$aDimensionVar[$key] = strlen($value);
    						}
    						
    						// Insert de la données avec les slashes
    						$sTexteWrite .= "'".addslashes($value)."'";
    					}
    					$sTexteWrite .= "),\n";
    					
    					// Ligne suivante
    					$iNbLigneInsert++;
    					// Continue pour ne pas tester la codition suivante, on va plus vite
    					continue;
    				}
    				
    				// Si on est au début du fichier et qu'on ne l'a pas déjà fait pour un autre on init les nom des colonnes
    				// Et le tableau des compteurs de taille
    				if ($bInitDim && $iNumLigne == $iLigneNameColonne) {
    					// Init de tableaux
    					$nbColonne = count($data);
    					for ($i=0; $i<$nbColonne;$i++) {
    						$aDimensionVar[$i] = 1;
    						$aNameColonne[] = $data[$i];
    					}
    					
    					// Construction de la chaine de colonne pour les requetes d'insert
    					$sChaineColonne = "`".implode("`,`", $aNameColonne)."`";
    					$bInitDim = false;
    				}
    			}
    			// Fin du fichier on supprime la virgule et on ajoute le point virgule
    			$sTexteWrite = substr($sTexteWrite, 0, -2);
    			$sTexteWrite .= ";\n";

    			// On écrit les données dans le fichier
    			$this->_writeFile($fp, $sTexteWrite);
    			
    			// On ferme le fichier CSV en cours et on passe au suivant
    			fclose($handle);
    		}
    	}
    	// On ferme le fichier sql des données
    	fclose($fp);

    	// Construction du fichier de création de table
    	$output->writeln("Construction de la table des données");
    	// On ouvre le fichier
    	$fp = fopen(self::NAME_FILE_TABLE, 'w');
    	$this->_writeFile($fp, "CREATE TABLE ".self::NAME_TABLE." (");
    	// Construction des colonnes
    	$sTexteWrite = "";
    	foreach ($aNameColonne as $iKey => $sNameColonne) {
    		$sTexteWrite .= "`$sNameColonne` VARCHAR(".$aDimensionVar[$iKey].") NULL,\n";
    	}
    	$sTexteWrite = substr($sTexteWrite, 0, -2);
    	$this->_writeFile($fp, $sTexteWrite);
    	
    	$sTexteWrite = ") COLLATE='latin1_general_ci' ENGINE=InnoDB;";
    	$this->_writeFile($fp, $sTexteWrite);
    	fclose($fp);
    }
    
    /**
     * Construction dans le fichier
     * @param unknown $fp
     * @param unknown $sTexteWrite
     */
    private function _writeFile($fp, $sTexteWrite)
    {
    	fwrite($fp, $sTexteWrite."\n");
    }
    
}