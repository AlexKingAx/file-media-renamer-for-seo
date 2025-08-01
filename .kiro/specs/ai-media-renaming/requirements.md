# Requirements Document

## Introduction

Questa feature implementa l'integrazione dell'intelligenza artificiale nel plugin WordPress "File Media Renamer for SEO" per generare automaticamente nomi di file ottimizzati per la SEO basandosi sul contenuto dei media e sul contesto delle pagine in cui sono utilizzati. Il sistema supporterà sia la rinomina singola che quella bulk, utilizzando API esterne per l'analisi del contenuto e un sistema di crediti per la gestione dei costi.

## Requirements

### Requirement 1

**User Story:** Come amministratore del sito, voglio poter rinominare un singolo file media utilizzando l'AI, così da ottenere un nome SEO-ottimizzato basato sul contenuto del file e sul contesto della pagina.

#### Acceptance Criteria

1. WHEN l'utente visualizza un allegato nella media library THEN il sistema SHALL mostrare un pulsante "Rinomina con AI" accanto al pulsante esistente "Save SEO Name"
2. WHEN l'utente clicca su "Rinomina con AI" THEN il sistema SHALL analizzare il contenuto del file e il contesto delle pagine che lo utilizzano
3. WHEN l'analisi è completata THEN il sistema SHALL presentare 1-3 suggerimenti di nomi tra cui scegliere
4. WHEN l'utente seleziona un nome suggerito THEN il sistema SHALL rinominare il file utilizzando le funzioni esistenti del plugin
5. WHEN la rinomina è completata con successo THEN il sistema SHALL detrarre 1 credito dal saldo dell'utente

### Requirement 2

**User Story:** Come amministratore del sito, voglio poter rinominare più file contemporaneamente utilizzando l'AI, così da ottimizzare rapidamente grandi quantità di media.

#### Acceptance Criteria

1. WHEN l'utente seleziona più file nella media library THEN il sistema SHALL mostrare l'opzione "Rinomina con AI" nelle azioni bulk
2. WHEN l'utente avvia la rinomina bulk con AI THEN il sistema SHALL processare ogni file individualmente
3. WHEN ogni file viene processato THEN il sistema SHALL generare un nome ottimizzato basato sul contenuto specifico del file
4. WHEN la rinomina bulk è completata THEN il sistema SHALL detrarre 1 credito per ogni file rinominato con successo
5. WHEN si verifica un errore su un file THEN il sistema SHALL continuare con i file rimanenti senza detrarre crediti per i file falliti

### Requirement 3

**User Story:** Come amministratore del sito, voglio che il sistema analizzi il contenuto dei miei file media, così da generare nomi appropriati basati su ciò che contengono.

#### Acceptance Criteria

1. WHEN il file è un'immagine THEN il sistema SHALL utilizzare OCR/vision API per estrarre testo e riconoscere oggetti
2. WHEN il file è un PDF THEN il sistema SHALL estrarre il testo utilizzando smalot/pdfparser
3. WHEN il file è un documento Office THEN il sistema SHALL estrarre il testo utilizzando phpoffice
4. WHEN il file è di un tipo non supportato THEN il sistema SHALL utilizzare solo i metadati WordPress esistenti (titolo, alt, MIME type)
5. WHEN l'estrazione del contenuto fallisce THEN il sistema SHALL procedere utilizzando solo i metadati disponibili

### Requirement 4

**User Story:** Come amministratore del sito, voglio che il sistema consideri il contesto delle pagine dove i media sono utilizzati, così da generare nomi più pertinenti al contenuto del sito.

#### Acceptance Criteria

1. WHEN un media è utilizzato in una o più pagine THEN il sistema SHALL recuperare gli ID dei post che incorporano l'allegato
2. WHEN vengono trovati post correlati THEN il sistema SHALL estrarre titoli, H-tag e parole chiave principali
3. WHEN sono presenti plugin SEO (Rank Math, Yoast) THEN il sistema SHALL includere i loro dati nelle analisi
4. WHEN sono attivi page builder THEN il sistema SHALL cercare contenuti anche nei post meta
5. WHEN non vengono trovati post correlati THEN il sistema SHALL procedere utilizzando solo il contenuto del file


### Requirement 5

**User Story:** Come amministratore del sito, voglio un sistema di crediti per controllare i costi dell'utilizzo dell'AI, così da gestire il budget in modo prevedibile.

#### Acceptance Criteria

1. WHEN l'utente inserisce una API key valida THEN il sistema SHALL mostrare il saldo crediti corrente nelle impostazioni
2. WHEN viene effettuata una richiesta AI con successo THEN il sistema SHALL detrarre 1 credito tramite chiamata POST /v1/credits/deduct
3. WHEN i crediti sono esauriti THEN il sistema SHALL mostrare errore "Crediti esauriti" e impedire nuove richieste AI
4. WHEN si verifica un timeout nella chiamata AI THEN il sistema SHALL utilizzare il fallback di rinomina base senza detrarre crediti
5. WHEN l'utente è nuovo THEN il sistema SHALL fornire 5 rinomine gratuite

### Requirement 6

**User Story:** Come amministratore del sito, voglio che il sistema mantenga sempre disponibili le funzionalità di rinomina esistenti, così da avere sempre un'alternativa funzionante.

#### Acceptance Criteria

1. WHEN l'AI non è disponibile THEN il sistema SHALL permettere sempre la rinomina manuale
2. WHEN l'API key non è configurata THEN il sistema SHALL nascondere le opzioni AI ma mantenere quelle esistenti
3. WHEN si verifica un errore nell'AI THEN il sistema SHALL offrire automaticamente il fallback alla rinomina base
4. WHEN WordPress non permette l'invio di media a servizi terzi THEN il sistema SHALL implementare soluzioni alternative locali
5. WHEN l'utente preferisce non usare l'AI THEN il sistema SHALL permettere di disabilitare completamente le funzionalità AI

### Requirement 7

**User Story:** Come amministratore del sito, voglio che tutte le operazioni AI siano registrate e tracciate, così da avere visibilità completa sull'utilizzo del sistema.

#### Acceptance Criteria

1. WHEN viene effettuata una rinomina AI THEN il sistema SHALL salvare l'operazione nello storico esistente
2. WHEN viene utilizzato un credito THEN il sistema SHALL registrare la transazione con timestamp e dettagli
3. WHEN si verifica un errore THEN il sistema SHALL loggare l'errore con informazioni di debug
4. WHEN l'utente visualizza lo storico THEN il sistema SHALL distinguere tra rinomine manuali e AI
5. WHEN vengono richieste statistiche THEN il sistema SHALL fornire report sull'utilizzo dei crediti e successo delle operazioni