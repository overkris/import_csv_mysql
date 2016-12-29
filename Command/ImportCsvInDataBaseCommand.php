<?php
namespace Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Input\InputArgument;

class ImportCsvInDataBaseCommand extends Command
{
	
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
    	// Get du path du rep
    	$pathRep = $input->getArgument('path_rep');
    	$output->writeln("Chargement des fichiers du dossier".$pathRep);
    	$output->writeln("");
    	
    	// Init du finder
    	$finder = new Finder();
    	$finder->files()->in($pathRep)->name('*.CSV')->name('*.csv');
    	
    	$output->writeln("Liste des fichiers fichier CSV pour ".$pathRep);
    	$aListeFiles = array();
    	foreach ($finder as $file) {
    		// Dump the absolute path
    		$output->writeln("  -  ".$file->getRelativePathname());
    		$aListeFiles[] = array("name" => $file->getRelativePathname(), "path" =>$file->getRealPath());
    	}
    	
    	$output->writeln("");
    	
    	// Init de la variable de dimension du tableau 
    	$aDimensionVar = array();
    	$aNameColonne = array();
    	$bInitDim = true;
    	$sChaineColonne = "";
    	$fp = fopen('data.sql', 'w');
    	
    	// Traitement des fichiers
    	foreach ($aListeFiles as $sValuePathFiles) {
    		$output->writeln("Construction des données pour le fichier ".$sValuePathFiles["name"]." ...");
    		
    		// Parse des fichier
    		$iNumLigne = 1;
    		// Ouverture du fichier
    		if (($handle = fopen($sValuePathFiles["path"], "r")) !== FALSE) {
    			// Traitement ligne à ligne
    			while (($data = fgetcsv($handle, null, ";")) !== FALSE) {
    				if ($iNumLigne > 2) {
    					$sTexteWrite = "INSERT INTO interventions ($sChaineColonne) VALUES";
    					$this->_writeFile($fp, $sTexteWrite);

    					$sTexteWrite = "  (";
    					foreach ($data as $key => $value) {
    						if (strlen($value) > $aDimensionVar[$key]) {
    							$aDimensionVar[$key] = strlen($value);
    						}
    						$sTexteWrite .= "'".addslashes($value)."', ";
    					}
    					$sTexteWrite = substr($sTexteWrite, 0, -2);
    					$sTexteWrite .= ");";
    					$this->_writeFile($fp, $sTexteWrite);
    					continue;
    				}
    				if ($bInitDim && $iNumLigne == 2) {
    					$nbColonne = count($data);
    					for ($i=0; $i<$nbColonne;$i++) {
    						$aDimensionVar[$i] = 1;
    						$aNameColonne[] = $data[$i];
    					}
    					
    					// Construction de la chaine de colonne
    					$sChaineColonne = "'".implode("','", $aNameColonne)."'";
    					$bInitDim = false;
    				}
    				$iNumLigne++;
    			}
    			fclose($handle);
    		}
    	}
    	fclose($fp);

    	// Construction de la table
    	$output->writeln("Construction de la table des données");
    	$fp = fopen('create_table.sql', 'w');
    	$sTexteWrite = "CREATE TABLE interventions (";
    	$this->_writeFile($fp, $sTexteWrite);
    	$sTexteWrite = "";
    	foreach ($aNameColonne as $iKey => $sNameColonne) {
    		$sTexteWrite .= "'$sNameColonne' VARCHAR(".$aDimensionVar[$iKey].") NULL,\n";
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