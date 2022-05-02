# language: it

Funzionalità: Inserimento proposte di voto per lo scrutinio
  Per inserire le proposte di voto dello scrutinio
  Come utente docente
  Bisogna inserire voti per cattedra di docente
  #-- Bisogna controllare filtro del periodo
  #-- Bisogna controllare visualizzazione proposte
  Bisogna controllare accesso a pagina


Contesto: login docente senza cattedre
	Dato login utente con ruolo esatto "Docente"
  E modifica istanze di tipo "Cattedra":
    | docente | #attiva |
    | #logged | no      |
  E modifica istanze di tipo "DefinizioneScrutinio":
    | #periodo |
    | -        |


################################################################################
# Bisogna inserire voti per cattedra di docente

Schema dello scenario: Inserisce e memorizza i voti senza recupero per la cattedra del docente
  Data creazione istanze di tipo "DefinizioneScrutinio":
    | id   | periodo   | data   | dataProposte |
    | $ds1 | <periodo> | #dtm() | #dtm()       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 4    | A       |
  E ricerca istanze di tipo "Materia":
    | id  | tipo           |
    | $m1 | <tipo_materia> |
  E istanze di tipo "Cattedra":
    | id  | docente | classe | materia | attiva | tipo |
    | $c1 | #logged | $cl1   | $m1     | si     | N    |
  E modifica istanze di tipo "Alunno":
    | classe | #abilitato |
    | $cl1   | no         |
  E istanze di tipo "Alunno":
    | id   | classe | nome     | cognome | abilitato |
    | $a1  | $cl1   | Mario    | Rossi   | si        |
    | $a2  | $cl1   | Giuseppe | Verdi   | si        |
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Quando premi pulsante "Aggiungi" con indice "2"
  E scorri cursore di "<posizioni>" posizioni
  E premi pulsante "Conferma"
  E vai alla pagina "login_home"
  E vai alla pagina "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Allora la sezione "#gs-main form #gs-form-collection li:contains('Verdi Giuseppe')" contiene "Voto <voto>"
  E la sezione "#gs-main form #gs-form-collection li:contains('Verdi Giuseppe') .form-group label" non contiene "Recupero"
  E la sezione "#gs-main form #gs-form-collection li:contains('Verdi Giuseppe') .form-group label" non contiene "Argomenti"
  Esempi:
    | periodo | tipo_materia | posizioni | voto          |
    | P       | N            | 0         | 6             |
    | P       | N            | 1         | 7             |
    | P       | N            | 2         | 8             |
    | P       | N            | 3         | 9             |
    | P       | N            | 4         | 10            |
    | P       | R            | -2        | NC            |
    | P       | R            | -1        | Insufficiente |
    | P       | R            | 0         | Sufficiente   |
    | P       | R            | 1         | Discreto      |
    | P       | R            | 2         | Buono         |
    | P       | R            | 3         | Distinto      |
    | P       | R            | 4         | Ottimo        |
    | P       | E            | 0         | 6             |
    | P       | E            | 1         | 7             |
    | P       | E            | 2         | 8             |
    | P       | E            | 3         | 9             |
    | P       | E            | 4         | 10            |
    | F       | N            | 0         | 6             |
    | F       | N            | 1         | 7             |
    | F       | N            | 2         | 8             |
    | F       | N            | 3         | 9             |
    | F       | N            | 4         | 10            |
    | F       | R            | -3        | NC            |
    | F       | R            | -2        | Insufficiente |
    | F       | R            | -1        | Mediocre      |
    | F       | R            | 0         | Sufficiente   |
    | F       | R            | 1         | Discreto      |
    | F       | R            | 2         | Buono         |
    | F       | R            | 3         | Distinto      |
    | F       | R            | 4         | Ottimo        |
    | F       | E            | 0         | 6             |
    | F       | E            | 1         | 7             |
    | F       | E            | 2         | 8             |
    | F       | E            | 3         | 9             |
    | F       | E            | 4         | 10            |

Schema dello scenario: Inserisce e memorizza i voti con recupero per la cattedra del docente
  Data creazione istanze di tipo "DefinizioneScrutinio":
    | id   | periodo   | data   | dataProposte |
    | $ds1 | <periodo> | #dtm() | #dtm()       |
  E ricerca istanze di tipo "Classe":
    | id   | anno | sezione |
    | $cl1 | 4    | A       |
  E ricerca istanze di tipo "Materia":
    | id  | tipo           |
    | $m1 | <tipo_materia> |
  E istanze di tipo "Cattedra":
    | id  | docente | classe | materia | attiva | tipo |
    | $c1 | #logged | $cl1   | $m1     | si     | N    |
  E modifica istanze di tipo "Alunno":
    | classe | #abilitato |
    | $cl1   | no         |
  E istanze di tipo "Alunno":
    | id   | classe | nome     | cognome | abilitato |
    | $a1  | $cl1   | Mario    | Rossi   | si        |
    | $a2  | $cl1   | Giuseppe | Verdi   | si        |
  E pagina attiva "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Quando premi pulsante "Aggiungi" con indice "2"
  E scorri cursore di "<posizioni>" posizioni
  E selezioni opzione "<recupero>" da lista "Recupero"
  E inserisci "<argomenti>" nel campo "Argomenti"
  E premi pulsante "Conferma"
  E vai alla pagina "login_home"
  E vai alla pagina "lezioni_scrutinio_proposte" con parametri:
    | cattedra | classe  | periodo   |
    | $c1:id   | $cl1:id | <periodo> |
  Allora la sezione "#gs-main form #gs-form-collection li:contains('Verdi Giuseppe')" contiene "Voto <voto>"
  E il campo "Recupero" contiene "<recupero_val>"
  E il campo "Argomenti" contiene "<argomenti>"
  Esempi:
    | periodo | tipo_materia | posizioni | voto | recupero            | recupero_val | argomenti           |
    | P       | N            | -6        | NC   | Corso di recupero   | C            | Da recuperare tutto |
    | P       | N            | -5        | 1    | Sportello didattico | S            | Da recuperare.      |
    | P       | N            | -4        | 2    | Pausa didattica     | P            | tutto               |
    | P       | N            | -3        | 3    | Studio individuale  | A            | Questo e quello.    |
    | P       | N            | -2        | 4    | Corso di recupero   | C            | Solo questo         |
    | P       | N            | -1        | 5    | Sportello didattico | S            | Qualcosina.         |
    | P       | E            | -3        | NC   | Corso di recupero   | C            | Da recuperare tutto |
    | P       | E            | -2        | 4    | Sportello didattico | S            | Tutto               |
    | P       | E            | -1        | 5    | Pausa didattica     | P            | Da recuperare       |
    | F       | N            | -6        | NC   | Corso di recupero   | C            | Da recuperare tutto |
    | F       | N            | -5        | 1    | Studio individuale  | A            | Da recuperare.      |
    | F       | N            | -4        | 2    | Corso di recupero   | C            | tutto               |
    | F       | N            | -3        | 3    | Studio individuale  | A            | Questo e quello.    |
    | F       | N            | -2        | 4    | Corso di recupero   | C            | Solo questo         |
    | F       | N            | -1        | 5    | Studio individuale  | A            | Qualcosina.         |
    | F       | E            | -4        | NC   | Studio individuale  | A            | Da recuperare tutto |
    | F       | E            | -3        | 3    | Corso di recupero   | C            | Tutto               |
    | F       | E            | -2        | 4    | Studio individuale  | A            | Molti argomenti     |
    | F       | E            | -1        | 5    | Corso di recupero   | C            | Da recuperare       |


################################################################################
# Bisogna controllare accesso a pagine

Scenario: mostra errore all'accesso alla pagina senza utente
  Dato logout utente
  Quando vai alla pagina "lezioni_scrutinio_proposte"
  Allora vedi pagina "login_form"

Schema dello scenario: mostra errore all'accesso alla pagina con altri utenti
  Dato logout utente
  E login utente con ruolo esatto "<ruolo>"
  Quando vai alla pagina "lezioni_scrutinio_proposte"
  Allora vedi errore pagina "403"
  Esempi:
    | ruolo          |
    | Amministratore |
    | Ata            |
    | Genitore       |
    | Alunno         |
    | Utente         |
