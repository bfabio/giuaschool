<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Controller;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Response;
use App\Form\RicercaType;
use App\Form\DocenteType;
use App\Form\ModuloType;
use App\Form\ImportaCsvType;
use App\Form\CattedraType;
use App\Form\ColloquioType;
use App\Util\CsvImporter;
use App\Util\LogHandler;
use App\Util\PdfManager;
use App\Util\StaffUtil;
use App\Entity\Docente;
use App\Entity\Cattedra;
use App\Entity\Colloquio;


/**
 * DocentiController - gestione docenti
 */
class DocentiController extends BaseController {

  /**
   * Importa docenti da file
   *
   * @param Request $request Pagina richiesta
   * @param SessionInterface $session Gestore delle sessioni
   * @param CsvImporter $importer Servizio per l'importazione dei dati da file CSV
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/importa/", name="docenti_importa",
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function importaAction(Request $request, SessionInterface $session, CsvImporter $importer): Response {
    // init
    $dati = [];
    $info = [];
    $var_sessione = '/APP/FILE/docenti_importa';
    $fs = new FileSystem();
    if (!$request->isMethod('POST')) {
      // cancella dati sessione
      $session->remove($var_sessione);
      // elimina file temporanei
      $finder = new Finder();
      $finder->in($this->getParameter('dir_tmp'))->date('< 1 day ago');
      foreach ($finder as $f) {
        $fs->remove($f);
      }
    }
    // form
    $form = $this->createForm(ImportaCsvType::class, null, ['formMode' => 'docenti']);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // trova file caricato
      $file = null;
      foreach ($session->get($var_sessione.'/file', []) as $f) {
        $file = new File($this->getParameter('dir_tmp').'/'.$f['temp']);
      }
      // importa file
      switch ($form->get('tipo')->getData()) {
        case 'U':
          // importa utenti
          $dati = $importer->importaDocenti($file, $form);
          break;
        case 'C':
          // importa cattedre
          $dati = $importer->importaCattedre($file, $form);
          break;
        case 'O':
          // importa orario
          $dati = $importer->importaOrario($file, $form);
          break;
        case 'L':
          // importa colloqui
          $dati = $importer->importaColloqui($file, $form);
          break;
      }
      $dati = ($dati == null ? [] : $dati);
      // cancella dati sessione
      $session->remove($var_sessione);
    }
    // visualizza pagina
    return $this->renderHtml('docenti', 'importa', $dati, $info, [$form->createView(),  'message.importa_docenti']);
  }

  /**
   * Gestisce la modifica dei dati dei docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @Route("/docenti/modifica/{pagina}", name="docenti_modifica",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function modificaAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                 TranslatorInterface $trans, $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['nome'] = $session->get('/APP/ROUTE/docenti_modifica/nome', '');
    $criteri['cognome'] = $session->get('/APP/ROUTE/docenti_modifica/cognome', '');
    $criteri['classe'] = $session->get('/APP/ROUTE/docenti_modifica/classe', 0);
    $classe = ($criteri['classe'] > 0 ? $em->getRepository('App:Classe')->find($criteri['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/docenti_modifica/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/docenti_modifica/pagina', $pagina);
    }
    // form di ricerca
    $lista_classi = $em->getRepository('App:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    $lista_classi[] = -1;
    $label_classe = $trans->trans('label.nessuna_classe');
    $form = $this->createForm(RicercaType::class, null, ['formMode' => 'docenti-alunni',
      'dati' => [$criteri['cognome'], $criteri['nome'], $classe, $lista_classi, $label_classe]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['nome'] = trim($form->get('nome')->getData());
      $criteri['cognome'] = trim($form->get('cognome')->getData());
      $criteri['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() :
        intval($form->get('classe')->getData()));
      $pagina = 1;
      $session->set('/APP/ROUTE/docenti_modifica/nome', $criteri['nome']);
      $session->set('/APP/ROUTE/docenti_modifica/cognome', $criteri['cognome']);
      $session->set('/APP/ROUTE/docenti_modifica/classe', $criteri['classe']);
      $session->set('/APP/ROUTE/docenti_modifica/pagina', $pagina);
    }
    // lista docenti
    $dati = $em->getRepository('App:Docente')->cerca($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'modifica', $dati, $info, [$form->createView()]);
  }

  /**
   * Abilitazione o disabilitazione dei docenti
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID dell'utente
   * @param int $abilita Valore 1 per abilitare, valore 0 per disabilitare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/abilita/{id}/{abilita}", name="docenti_abilita",
   *    requirements={"id": "\d+", "abilita": "0|1"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function abilitaAction(EntityManagerInterface $em, $id, $abilita): Response {
    // controllo docente
    $docente = $em->getRepository('App:Docente')->find($id);
    if (!$docente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // abilita o disabilita
    $docente->setAbilitato($abilita == 1);
    $em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('docenti_modifica');
  }

  /**
   * Modifica dei dati di un docente
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/modifica/edit/{id}", name="docenti_modifica_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function modificaEditAction(Request $request, EntityManagerInterface $em, $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $docente = $em->getRepository('App:Docente')->find($id);
      if (!$docente) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $docente = (new Docente())
        ->setAbilitato(true)
        ->setPassword('NOPASSWORD');
      $em->persist($docente);
    }
    // form
    $form = $this->createForm(DocenteType::class, $docente, ['returnUrl' => $this->generateUrl('docenti_modifica')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza modifiche
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('docenti_modifica');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'modifica_edit', [], [], [$form->createView(), 'message.required_fields']);
   }

  /**
   * Genera una nuova password e la invia all'utente docente
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param UserPasswordEncoderInterface $encoder Gestore della codifica delle password
   * @param SessionInterface $session Gestore delle sessioni
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param StaffUtil $staff Funzioni disponibili allo staff
   * @param \Swift_Mailer $mailer Gestore della spedizione delle email
   * @param LoggerInterface $logger Gestore dei log su file
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/password/{id}", name="docenti_password",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function passwordAction(Request $request, EntityManagerInterface $em,
                                 UserPasswordEncoderInterface $encoder, SessionInterface $session,
                                 PdfManager $pdf, StaffUtil $staff, \Swift_Mailer $mailer, LoggerInterface $logger,
                                 LogHandler $dblogger,  $id): Response {
    // controlla docente
    $docente = $em->getRepository('App:Docente')->find($id);
    if (!$docente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // crea password
    $password = $staff->creaPassword(10);
    $docente->setPasswordNonCifrata($password);
    $pswd = $encoder->encodePassword($docente, $docente->getPasswordNonCifrata());
    $docente->setPassword($pswd);
    // memorizza su db
    $em->flush();
    // log azione
    $dblogger->write($docente, $request->getClientIp(), 'SICUREZZA', 'Generazione Password', __METHOD__, array(
      'Username esecutore' => $this->getUser()->getUsername(),
      'Ruolo esecutore' => $this->getUser()->getRoles()[0],
      'ID esecutore' => $this->getUser()->getId()));
    // crea documento PDF
    $pdf->configure($session->get('/CONFIG/ISTITUTO/intestazione'),
      'Credenziali di accesso al Registro Elettronico');
    // contenuto in formato HTML
    $html = $this->renderView('pdf/credenziali_docenti.html.twig', array(
      'docente' => $docente,
      'password' => $password));
    $pdf->createFromHtml($html);
    $html = $this->renderView('pdf/credenziali_privacy.html.twig', array(
      'utente' => $docente));
    $pdf->createFromHtml($html);
    $doc = $pdf->getHandler()->Output('', 'S');
    // crea il messaggio
    $message = (new \Swift_Message())
      ->setSubject($session->get('/CONFIG/ISTITUTO/intestazione_breve')." - Credenziali di accesso al Registro Elettronico")
      ->setFrom([$session->get('/CONFIG/ISTITUTO/email_notifiche') => $session->get('/CONFIG/ISTITUTO/intestazione_breve')])
      ->setTo([$docente->getEmail()])
      ->setBody($this->renderView('email/credenziali.html.twig'), 'text/html')
      ->addPart($this->renderView('email/credenziali.txt.twig'), 'text/plain')
      ->attach(new \Swift_Attachment($doc, 'credenziali_registro.pdf', 'application/pdf'));
    // invia mail
    if (!$mailer->send($message)) {
      // errore di spedizione
      $logger->error('Errore di spedizione email delle credenziali docente.', array(
        'username' => $docente->getUsername(),
        'email' => $docente->getEmail(),
        'ip' => $request->getClientIp()));
      $this->addFlash('danger', 'exception.errore_invio_credenziali');
    } else {
      // tutto ok
      $this->addFlash('success', 'message.credenziali_inviate');
    }
    // redirezione
    return $this->redirectToRoute('docenti_modifica');
  }

  /**
   * Reset della funzione OTP per i docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/otp/{id}", name="docenti_reset",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function resetAction(Request $request, EntityManagerInterface $em,
                              LogHandler $dblogger, $id): Response {
    // controlla docente
    $docente = $em->getRepository('App:Docente')->find($id);
    if (!$docente) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // reset OTP
    $docente->setOtp(null);
    $em->flush();
    // log azione
    $dblogger->write($docente, $request->getClientIp(), 'SICUREZZA', 'Reset OTP', __METHOD__, array(
      'Username esecutore' => $this->getUser()->getUsername(),
      'Ruolo esecutore' => $this->getUser()->getRoles()[0],
      'ID esecutore' => $this->getUser()->getId()));
    // messaggio ok
    $this->addFlash('success', 'message.credenziali_inviate');
    // redirezione
    return $this->redirectToRoute('docenti_modifica');
  }

  /**
   * Gestione dell'assegnamento del ruolo di staff
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/staff/", name="docenti_staff",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function staffAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                              $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['nome'] = $session->get('/APP/ROUTE/docenti_staff/nome', '');
    $criteri['cognome'] = $session->get('/APP/ROUTE/docenti_staff/cognome', '');
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/docenti_staff/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/docenti_staff/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->createForm(RicercaType::class, null, ['formMode' => 'utenti',
      'dati' => [$criteri['cognome'], $criteri['nome']]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['nome'] = trim($form->get('nome')->getData());
      $criteri['cognome'] = trim($form->get('cognome')->getData());
      $pagina = 1;
      $session->set('/APP/ROUTE/docenti_staff/nome', $criteri['nome']);
      $session->set('/APP/ROUTE/docenti_staff/cognome', $criteri['cognome']);
      $session->set('/APP/ROUTE/docenti_staff/pagina', $pagina);
    }
    // lista staff
    $dati = $em->getRepository('App:Staff')->cerca($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'staff', $dati, $info, [$form->createView()]);
  }

  /**
   * Modifica dei dati di configurazione dello staff
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/staff/edit/{id}", name="docenti_staff_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function staffEditAction(Request $request, EntityManagerInterface $em, $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $staff = $em->getRepository('App:Staff')->find($id);
      if (!$staff) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $sede = $staff->getSede();
    } else {
      // azione add
      $staff = null;
      $sede = null;
    }
    // form
    $form = $this->createForm(ModuloType::class, null, ['formMode' => 'staff',
      'returnUrl' => $this->generateUrl('docenti_staff'), 'dati' => [$staff, $sede] ]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      if ($staff) {
        // modifica
        $staff->setSede($form->get('sede')->getData());
        $em->flush();
      } else {
        // nuovo
        $docente_id = $form->get('docente')->getData()->getId();
        $sede_id = ($form->get('sede')->getData() ? $form->get('sede')->getData()->getId() : null);
        // cambia ruolo in staff
        $sql = "UPDATE gs_utente SET modificato=NOW(),ruolo=:ruolo,sede_id=:sede WHERE id=:id";
        $params = array('ruolo' => 'STA', 'sede' => $sede_id, 'id' => $docente_id);
        $em->getConnection()->prepare($sql)->execute($params);
      }
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('docenti_staff');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'staff_edit', [], [], [$form->createView(), 'message.required_fields']);
   }

  /**
   * Cancellazione del componente dello staff
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/staff/delete/{id}", name="docenti_staff_delete", requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
   public function staffDeleteAction(EntityManagerInterface $em, $id): Response {
    // controlla utente staff
    $staff = $em->getRepository('App:Staff')->find($id);
    if (!$staff) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // toglie il ruolo di staff
    $sql = "UPDATE gs_utente SET modificato=NOW(),ruolo=:ruolo,sede_id=:sede WHERE id=:id";
    $params = array('ruolo' => 'DOC', 'sede' => null, 'id' => $staff->getId());
    $em->getConnection()->prepare($sql)->execute($params);
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('docenti_staff');
  }

  /**
   * Gestione dell'assegnamento del ruolo di coordinatore
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/coordinatori/", name="docenti_coordinatori",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function coordinatoriAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                     $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['nome'] = $session->get('/APP/ROUTE/docenti_coordinatori/nome', '');
    $criteri['cognome'] = $session->get('/APP/ROUTE/docenti_coordinatori/cognome', '');
    $criteri['classe'] = $session->get('/APP/ROUTE/docenti_coordinatori/classe', 0);
    $classe = ($criteri['classe'] > 0 ? $em->getRepository('App:Classe')->find($criteri['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/docenti_coordinatori/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/docenti_coordinatori/pagina', $pagina);
    }
    // form di ricerca
    $lista_classi = $em->getRepository('App:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    $form = $this->createForm(RicercaType::class, null, ['formMode' => 'docenti-alunni',
      'dati' => [$criteri['cognome'], $criteri['nome'], $classe, $lista_classi, null]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['nome'] = trim($form->get('nome')->getData());
      $criteri['cognome'] = trim($form->get('cognome')->getData());
      $criteri['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() :
        intval($form->get('classe')->getData()));
      $pagina = 1;
      $session->set('/APP/ROUTE/docenti_coordinatori/nome', $criteri['nome']);
      $session->set('/APP/ROUTE/docenti_coordinatori/cognome', $criteri['cognome']);
      $session->set('/APP/ROUTE/docenti_coordinatori/pagina', $pagina);
      $session->set('/APP/ROUTE/docenti_coordinatori/classe', $criteri['classe']);
    }
    // lista coordinatori
    $dati = $em->getRepository('App:Classe')->cercaCoordinatori($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'coordinatori', $dati, $info, [$form->createView()]);
  }

  /**
   * Modifica dei dati di configurazione del coordinatore di classe
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/coordinatori/edit/{id}", name="docenti_coordinatori_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function coordinatoriEditAction(Request $request, EntityManagerInterface $em, $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $classe = $em->getRepository('App:Classe')->find($id);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $docente = $classe->getCoordinatore();
    } else {
      // azione add
      $classe = null;
      $docente = null;
    }
    // form
    $form = $this->createForm(ModuloType::class, null, ['formMode' => 'coordinatori',
      'returnUrl' => $this->generateUrl('docenti_coordinatori'), 'dati' => [$classe, $docente] ]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza
      $classe = $form->get('classe')->getData();
      $docente = $form->get('docente')->getData();
      $classe->setCoordinatore($docente);
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('docenti_coordinatori');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'coordinatori_edit', [], [], [$form->createView(), 'message.required_fields']);
   }

  /**
   * Gestione della cancellazione del ruolo di coordinatore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/coordinatori/delete/{id}", name="docenti_coordinatori_delete", requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function coordinatoriDeleteAction(EntityManagerInterface $em, $id) {
    // controlla classe
    $classe = $em->getRepository('App:Classe')->find($id);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // toglie coordinatore
    $classe->setCoordinatore(null);
    $em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('docenti_coordinatori');
  }

  /**
   * Gestione dell'assegnamento del ruolo di segretario
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/segretari/", name="docenti_segretari",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function segretariAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                  $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['nome'] = $session->get('/APP/ROUTE/docenti_segretari/nome', '');
    $criteri['cognome'] = $session->get('/APP/ROUTE/docenti_segretari/cognome', '');
    $criteri['classe'] = $session->get('/APP/ROUTE/docenti_segretari/classe', 0);
    $classe = ($criteri['classe'] > 0 ? $em->getRepository('App:Classe')->find($criteri['classe']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/docenti_segretari/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/docenti_segretari/pagina', $pagina);
    }
    // form di ricerca
    $lista_classi = $em->getRepository('App:Classe')->findBy([], ['anno' =>'ASC', 'sezione' =>'ASC']);
    $form = $this->createForm(RicercaType::class, null, ['formMode' => 'docenti-alunni',
      'dati' => [$criteri['cognome'], $criteri['nome'], $classe, $lista_classi, null]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['nome'] = trim($form->get('nome')->getData());
      $criteri['cognome'] = trim($form->get('cognome')->getData());
      $criteri['classe'] = (is_object($form->get('classe')->getData()) ? $form->get('classe')->getData()->getId() :
        intval($form->get('classe')->getData()));
      $pagina = 1;
      $session->set('/APP/ROUTE/docenti_segretari/nome', $criteri['nome']);
      $session->set('/APP/ROUTE/docenti_segretari/cognome', $criteri['cognome']);
      $session->set('/APP/ROUTE/docenti_segretari/pagina', $pagina);
      $session->set('/APP/ROUTE/docenti_segretari/classe', $criteri['classe']);
    }
    // lista segretari
    $dati = $em->getRepository('App:Classe')->cercaSegretari($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'segretari', $dati, $info, [$form->createView()]);
  }

  /**
   * Modifica dei dati di configurazione del segretario di classe
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/segretari/edit/{id}", name="docenti_segretari_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function segretariEditAction(Request $request, EntityManagerInterface $em, $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $classe = $em->getRepository('App:Classe')->find($id);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $docente = $classe->getSegretario();
    } else {
      // azione add
      $classe = null;
      $docente = null;
    }
    // form
    $form = $this->createForm(ModuloType::class, null, ['formMode' => 'coordinatori',
      'returnUrl' => $this->generateUrl('docenti_segretari'), 'dati' => [$classe, $docente] ]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // memorizza
      $classe = $form->get('classe')->getData();
      $docente = $form->get('docente')->getData();
      $classe->setSegretario($docente);
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
      // redirect
      return $this->redirectToRoute('docenti_segretari');
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'segretari_edit', [], [], [$form->createView(), 'message.required_fields']);
   }

  /**
   * Gestione della cancellazione del ruolo di segretario
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID dell'utente
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/segretari/delete/{id}", name="docenti_segretari_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function segretariDeleteAction(EntityManagerInterface $em, $id) {
    // controlla classe
    $classe = $em->getRepository('App:Classe')->find($id);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // toglie coordinatore
    $classe->setSegretario(null);
    $em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('docenti_segretari');
  }

  /**
   * Gestisce la modifica delle cattedre dei docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @Route("/docenti/cattedre/{pagina}", name="docenti_cattedre",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function cattedreAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                 $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['classe'] = $session->get('/APP/ROUTE/docenti_cattedre/classe', 0);
    $criteri['materia'] = $session->get('/APP/ROUTE/docenti_cattedre/materia', 0);
    $criteri['docente'] = $session->get('/APP/ROUTE/docenti_cattedre/docente', 0);
    $classe = ($criteri['classe'] > 0 ? $em->getRepository('App:Classe')->find($criteri['classe']) : 0);
    $materia = ($criteri['materia'] > 0 ? $em->getRepository('App:Materia')->find($criteri['materia']) : 0);
    $docente = ($criteri['docente'] > 0 ? $em->getRepository('App:Docente')->find($criteri['docente']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/docenti_cattedre/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/docenti_cattedre/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->createForm(RicercaType::class, null, ['formMode' => 'cattedre',
      'dati' => [$classe, $materia, $docente]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['classe'] = ($form->get('classe')->getData() ? $form->get('classe')->getData()->getId() : 0);
      $criteri['materia'] = ($form->get('materia')->getData() ? $form->get('materia')->getData()->getId() : 0);
      $criteri['docente'] = ($form->get('docente')->getData() ? $form->get('docente')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/docenti_cattedre/classe', $criteri['classe']);
      $session->set('/APP/ROUTE/docenti_cattedre/materia', $criteri['materia']);
      $session->set('/APP/ROUTE/docenti_cattedre/docente', $criteri['docente']);
      $session->set('/APP/ROUTE/docenti_cattedre/pagina', $pagina);
    }
    // lista cattedre
    $dati = $em->getRepository('App:Cattedra')->cerca($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'cattedre', $dati, $info, [$form->createView()]);
  }

  /**
   * Crea o modifica una cattedra di un docente
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $id ID della cattedra
   *
   * @Route("/docenti/cattedre/edit/{id}", name="docenti_cattedre_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function cattedreEditAction(Request $request, EntityManagerInterface $em,
                                     TranslatorInterface $trans, $id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $cattedra = $em->getRepository('App:Cattedra')->find($id);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // azione add
      $cattedra = (new Cattedra())
        ->setAttiva(true);
      $em->persist($cattedra);
    }
    // form
    $form = $this->createForm(CattedraType::class, $cattedra, ['returnUrl' => $this->generateUrl('docenti_cattedre')]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      if ($cattedra->getMateria()->getTipo() == 'S') {
        // sostegno
        if ($cattedra->getAlunno() && $cattedra->getAlunno()->getClasse() != $cattedra->getClasse()) {
          // classe diversa da quella di alunno
          $form->get('classe')->addError(new FormError($trans->trans('exception.classe_errata')));
        }
      } else {
        // materia non è sostegno, nessun alunno deve essere presente
        $cattedra->setAlunno(null);
      }
      if ($id == 0) {
        // controlla esistenza di cattedra
        $lista = $em->getRepository('App:Cattedra')->findBy(array(
          'docente' => $cattedra->getDocente(),
          'classe' => $cattedra->getClasse(),
          'materia' => $cattedra->getMateria(),
          'alunno' => $cattedra->getAlunno()));
        if (count($lista) > 0) {
          // cattedra esiste già
          $form->addError(new FormError($trans->trans('exception.cattedra_esiste')));
        }
      }
      if ($form->isValid()) {
        // memorizza dati
        $em->flush();
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirect
        return $this->redirectToRoute('docenti_cattedre');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'cattedre_edit', [], [], [$form->createView(), 'message.required_fields']);
  }

  /**
   * Abilitazione o disabilitazione delle cattedre
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID della cattedra
   * @param int $abilita Valore 1 per abilitare, valore 0 per disabilitare
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/cattedre/abilita/{id}/{abilita}", name="docenti_cattedre_abilita",
   *    requirements={"id": "\d+", "abilita": "0|1"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function cattedreEnableAction(EntityManagerInterface $em, $id, $abilita): Response {
    // controllo cattedra
    $cattedra = $em->getRepository('App:Cattedra')->find($id);
    if (!$cattedra) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // abilita o disabilita
    $cattedra->setAttiva($abilita == 1);
    $em->flush();
    // messaggio
    $this->addFlash('success', 'message.update_ok');
    // redirezione
    return $this->redirectToRoute('docenti_cattedre');
  }

  /**
   * Gestisce la modifica dei dati dei docenti
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $pagina Numero di pagina per la lista visualizzata
   *
   * @Route("/docenti/colloqui/{pagina}", name="docenti_colloqui",
   *    requirements={"pagina": "\d+"},
   *    defaults={"pagina": 0},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function colloquiAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                 TranslatorInterface $trans, $pagina): Response {
    // init
    $dati = [];
    $info = [];
    // recupera criteri dalla sessione
    $criteri = array();
    $criteri['sede'] = $session->get('/APP/ROUTE/docenti_colloqui/sede', 0);
    $criteri['classe'] = $session->get('/APP/ROUTE/docenti_colloqui/classe', 0);
    $criteri['docente'] = $session->get('/APP/ROUTE/docenti_colloqui/docente', 0);
    $sede = ($criteri['sede'] > 0 ? $em->getRepository('App:Sede')->find($criteri['sede']) : 0);
    $classe = ($criteri['classe'] > 0 ? $em->getRepository('App:Classe')->find($criteri['classe']) : 0);
    $docente = ($criteri['docente'] > 0 ? $em->getRepository('App:Docente')->find($criteri['docente']) : 0);
    if ($pagina == 0) {
      // pagina non definita: la cerca in sessione
      $pagina = $session->get('/APP/ROUTE/docenti_colloqui/pagina', 1);
    } else {
      // pagina specificata: la conserva in sessione
      $session->set('/APP/ROUTE/docenti_colloqui/pagina', $pagina);
    }
    // form di ricerca
    $form = $this->createForm(RicercaType::class, null, ['formMode' => 'docenti-sedi',
      'dati' => [$sede, $classe, $docente]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta criteri di ricerca
      $criteri['sede'] = ($form->get('sede')->getData() ? $form->get('sede')->getData()->getId() : 0);
      $criteri['classe'] = ($form->get('classe')->getData() ? $form->get('classe')->getData()->getId() : 0);
      $criteri['docente'] = ($form->get('docente')->getData() ? $form->get('docente')->getData()->getId() : 0);
      $pagina = 1;
      $session->set('/APP/ROUTE/docenti_colloqui/sede', $criteri['sede']);
      $session->set('/APP/ROUTE/docenti_colloqui/classe', $criteri['classe']);
      $session->set('/APP/ROUTE/docenti_colloqui/docente', $criteri['docente']);
      $session->set('/APP/ROUTE/docenti_colloqui/pagina', $pagina);
    }
    // lista colloqui
    $dati = $em->getRepository('App:Colloquio')->cerca($criteri, $pagina);
    $info['pagina'] = $pagina;
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'colloqui', $dati, $info, [$form->createView()]);
  }

  /**
   * Modifica dei dati di configurazione di un colloquio
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param TranslatorInterface $trans Gestore delle traduzioni
   * @param int $id ID del colloquio
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/colloqui/edit/{id}", name="docenti_colloqui_edit",
   *    requirements={"id": "\d+"},
   *    defaults={"id": "0"},
   *    methods={"GET", "POST"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function colloquiEditAction(Request $request, EntityManagerInterface $em,
                                     TranslatorInterface $trans,$id): Response {
    // controlla azione
    if ($id > 0) {
      // azione edit
      $colloquio = $em->getRepository('App:Colloquio')->find($id);
      if (!$colloquio) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $sede = $colloquio->getOrario()->getSede();
      $docente = $colloquio->getDocente();
    } else {
      // azione add
      $colloquio = new Colloquio();
      $orario = $em->getRepository('App:Orario')->orarioSede();
      $colloquio->setOrario($orario);
      $em->persist($colloquio);
      $sede = null;
      $docente = null;
    }
    // form
    $form = $this->createForm(ColloquioType::class, $colloquio,
      ['returnUrl' => $this->generateUrl('docenti_colloqui'), 'dati' => [$sede, $docente]]);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta orario di sede
      $orario = $em->getRepository('App:Orario')->orarioSede($form->get('sede')->getData());
      if (!$orario) {
        // errore: orario di sede non esiste
        $form->addError(new FormError($trans->trans('exception.orario_sede_invalido')));
      } else {
        // imposta orario
        $colloquio->setOrario($orario);
      }
      // controlla se esiste orario indicato
      $ora = $em->getRepository('App:ScansioneOraria')->findOneBy(['orario' => $orario,
        'giorno' => $colloquio->getGiorno(), 'ora' => $colloquio->getOra()]);
      if (!$ora) {
        // errore: ora non esiste
        $form->addError(new FormError($trans->trans('exception.scansione_ora_invalida')));
      }
      if ($id == 0 && $orario) {
        // controlla se esiste già il colloquio
        $col = $em->getRepository('App:Colloquio')->findOneBy(['orario' => $orario,
          'docente' => $colloquio->getDocente()]);
        if ($col) {
          // errore: ora non esiste
          $form->addError(new FormError($trans->trans('exception.colloquio_duplicato')));
        }
      }
      if ($form->isValid()) {
        // ok, memorizza
        $em->flush();
        // messaggio
        $this->addFlash('success', 'message.update_ok');
        // redirect
        return $this->redirectToRoute('docenti_colloqui');
      }
    }
    // mostra la pagina di risposta
    return $this->renderHtml('docenti', 'colloqui_edit', [], [], [$form->createView(), 'message.required_fields']);
  }

  /**
   * Gestione della cancellazione del colloquio
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param int $id ID della classe
   *
   * @return Response Pagina di risposta
   *
   * @Route("/docenti/colloqui/delete/{id}", name="docenti_colloqui_delete",
   *    requirements={"id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function colloquiDeleteAction(EntityManagerInterface $em, $id) {
    // controlla colloquio
    $colloquio = $em->getRepository('App:Colloquio')->find($id);
    if (!$colloquio) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla presenza di richieste
    $ric = $em->getRepository('App:RichiestaColloquio')->findOneByColloquio($colloquio);
    if ($ric) {
      // errore
      $this->addFlash('danger', 'exception.dati_presenti');
    } else {
      // elimina colloquio
      $em->remove($colloquio);
      $em->flush();
      // messaggio
      $this->addFlash('success', 'message.update_ok');
    }
    // redirezione
    return $this->redirectToRoute('docenti_colloqui');
  }

}
