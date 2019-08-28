<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
Use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;
use App\Entity\Configurazione;
use App\Entity\Istituto;


/**
 * ConfigLoader - classe di utilità per il caricamento dei parametri dal db nella sessione corrente
 */
class ConfigLoader {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;

  /**
   * @var SessionInterface $session Gestore delle sessioni
   */
  private $session;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   */
  public function __construct(EntityManagerInterface $em, SessionInterface $session) {
    $this->em = $em;
    $this->session = $session;
  }

  /**
   * Legge la configurazione relativa alla categoria indicata e la ricarica nella sessione
   *
   * @param string $categoria Categoria della configurazione
   */
  public function load($categoria) {
    // rimuove la configurazione esistente
    $this->session->remove('/CONFIG/'.$categoria);
    if ($categoria == 'ISTITUTO') {
      // carica dati dall'entità Istituto
      $this->caricaIstituto();
    } else {
      // carica dati dall'entità Configurazione
      $list = $this->em->getRepository('App:Configurazione')->findByCategoria($categoria);
      foreach ($list as $item) {
        $this->session->set('/CONFIG/'.$categoria.'/'.$item->getParametro(), $item->getValore());
      }
    }
  }

  /**
   * Legge tutta la configurazione e la memorizza nella sessione
   */
  public function loadAll() {
    // rimuove la configurazione esistente
    $this->session->remove('/CONFIG');
    // carica dati dall'entità Configurazione
    $list = $this->em->getRepository('App:Configurazione')->findAll();
    foreach ($list as $item) {
      $this->session->set('/CONFIG/'.$item->getCategoria().'/'.$item->getParametro(), $item->getValore());
    }
    // carica dati dall'entità Istituto
    $this->caricaIstituto();
  }

  /**
   * Carica la configurazione dall'entità Istituto
   */
  private function caricaIstituto() {
    $istituto = $this->em->getRepository('App:Istituto')->findAll();
    if (count($istituto) > 0) {
      $this->session->set('/CONFIG/ISTITUTO/tipo', $istituto[0]->getTipo());
      $this->session->set('/CONFIG/ISTITUTO/tipo_sigla', $istituto[0]->getTipoSigla());
      $this->session->set('/CONFIG/ISTITUTO/nome', $istituto[0]->getNome());
      $this->session->set('/CONFIG/ISTITUTO/nome_breve', $istituto[0]->getNomeBreve());
      $this->session->set('/CONFIG/ISTITUTO/intestazione', $istituto[0]->getIntestazione());
      $this->session->set('/CONFIG/ISTITUTO/intestazione_breve', $istituto[0]->getIntestazioneBreve());
      $this->session->set('/CONFIG/ISTITUTO/email', $istituto[0]->getEmail());
      $this->session->set('/CONFIG/ISTITUTO/pec', $istituto[0]->getPec());
      $this->session->set('/CONFIG/ISTITUTO/url_sito', $istituto[0]->getUrlSito());
      $this->session->set('/CONFIG/ISTITUTO/url_registro', $istituto[0]->getUrlRegistro());
      $this->session->set('/CONFIG/ISTITUTO/firma_preside', $istituto[0]->getFirmaPreside());
      $this->session->set('/CONFIG/ISTITUTO/email_amministratore', $istituto[0]->getEmailAmministratore());
      $this->session->set('/CONFIG/ISTITUTO/email_notifiche', $istituto[0]->getEmailNotifiche());
    }
  }

}