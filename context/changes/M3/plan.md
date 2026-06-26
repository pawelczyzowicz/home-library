# Plan: M3 — Izolacja danych — książki i półki per biblioteka

---
change-id: M3
status: done
phases: 5
---

## End State

Po implementacji M3:
- Encje `Book` i `Shelf` mają relację ManyToOne do `Library` z kolumną `library_id` (FK NOT NULL)
- Wszystkie zapytania do repozytoriów filtrują dane po `library_id` zalogowanego użytkownika
- Nowo tworzone książki i półki automatycznie otrzymują `library_id` z sesji użytkownika
- Użytkownik nie może zobaczyć ani zmodyfikować danych innej biblioteki (nawet przez manipulację URL/ID)
- Półki systemowe istnieją per biblioteka (nie globalnie)
- UNIQUE constraint na `shelves.name` zmieniony na `shelves(library_id, name)` — ta sama nazwa półki w różnych bibliotekach jest dozwolona
- AI recommendations filtruje kontekst książek per library
- Istniejące testy przechodzą po aktualizacji

### Kluczowa decyzja architektoniczna

**Jawne przekazywanie `UuidInterface $libraryId`** przez Commands/Queries → Handlers → Repositories.

Uzasadnienie: bardziej explicite niż Doctrine SQL Filter, łatwiejsze w testowaniu, spójne z istniejącym wzorcem command/handler. UI layer wyciąga library z authenticated User i przekazuje w dół.

---

## Phase 1 — Domain: Relacja Library w Book i Shelf + rozszerzenie interfejsów

**Goal:** Dodać relację ManyToOne do Library w encjach Book i Shelf oraz rozszerzyć interfejsy repozytoriów o parametr `libraryId`.

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `src/HomeLibrary/Domain/Book/Book.php` | MODIFY | Dodać pole `private Library $library` z `#[ORM\ManyToOne]` + `#[ORM\JoinColumn(name: 'library_id', nullable: false)]`. Dodać parametr `Library $library` w konstruktorze. Dodać getter `library(): Library`. |
| `src/HomeLibrary/Domain/Book/BookRepository.php` | MODIFY | Rozszerzyć `search()` o parametr `UuidInterface $libraryId` (pierwszy parametr). Rozszerzyć `findById()` o opcjonalny `?UuidInterface $libraryId = null` — jeśli podany, waliduje przynależność. |
| `src/HomeLibrary/Domain/Shelf/Shelf.php` | MODIFY | Dodać pole `private Library $library` z `#[ORM\ManyToOne]` + `#[ORM\JoinColumn(name: 'library_id', nullable: false)]`. Dodać parametr `Library $library` w konstruktorze. Dodać getter `library(): Library`. |
| `src/HomeLibrary/Domain/Shelf/ShelfRepository.php` | MODIFY | Rozszerzyć `search()` o parametr `UuidInterface $libraryId` (pierwszy parametr). Rozszerzyć `countBySearchTerm()` o parametr `UuidInterface $libraryId`. Rozszerzyć `findById()` o opcjonalny `?UuidInterface $libraryId = null`. |

### Verification

- [ ] `bin/console lint:container` passes
- [ ] No PHPStan errors at configured level
- [ ] Encja Book ma relację do Library
- [ ] Encja Shelf ma relację do Library
- [ ] Interfejsy repozytoriów akceptują libraryId

### Notes

- `findById($id, $libraryId)` z podanym libraryId zwraca `null` jeśli entity nie należy do tej biblioteki → zapobiega horizontal privilege escalation
- Book ma bezpośredni `library_id` mimo redundancji z `shelf.library_id` — performance (nie wymaga JOIN do shelf) + security (double-check)
- Genre NIE wymaga `library_id` — gatunki są globalne
- Konstruktory Book i Shelf wymagają Library — backward compat zapewniona w Phase 5 (testy)

---

## Phase 2 — Infrastructure: Migracja + aktualizacja repozytoriów

**Goal:** Dodać migrację DB z kolumną `library_id` w tabelach `books` i `shelves` oraz zaktualizować implementacje repozytoriów o filtrowanie per library.

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `migrations/Version20260624120000.php` | CREATE | 1) Dodaje `library_id UUID NOT NULL` do `books` z FK do `libraries(id)` + INDEX. 2) Dodaje `library_id UUID NOT NULL` do `shelves` z FK do `libraries(id)` + INDEX. 3) Drop UNIQUE na `shelves.name`, dodaje UNIQUE na `shelves(library_id, name)`. 4) Dla istniejących danych: przypisuje default library (ta sama co w M1 migracji). |
| `src/HomeLibrary/Infrastructure/Persistence/DoctrineBookRepository.php` | MODIFY | 1) `search()` — dodać parametr `UuidInterface $libraryId`, dodać `->andWhere('b.library = :libraryId')->setParameter('libraryId', $libraryId)`. 2) `findById()` — dodać opcjonalny `?UuidInterface $libraryId`, jeśli podany: filtruj po library. |
| `src/HomeLibrary/Infrastructure/Persistence/DoctrineShelfRepository.php` | MODIFY | 1) `search()` — dodać parametr `UuidInterface $libraryId`, dodać `->andWhere('s.library = :libraryId')->setParameter('libraryId', $libraryId)`. 2) `countBySearchTerm()` — analogicznie. 3) `findById()` — dodać opcjonalny `?UuidInterface $libraryId`, jeśli podany: filtruj po library. |
| `src/HomeLibrary/Infrastructure/Persistence/DbalShelfBooksCounter.php` | MODIFY | Dodać `AND library_id = :library_id` do SQL. Rozszerzyć `countForShelf()` o parametr `UuidInterface $libraryId`. |
| `src/HomeLibrary/Infrastructure/AI/DoctrineBookReadRepository.php` | MODIFY | Dodać `AND library_id = :library_id` do SQL w `find()`. Rozszerzyć interfejs `BookReadRepository` + implementację o parametr `UuidInterface $libraryId`. |
| `src/HomeLibrary/Application/AI/ReadModel/BookReadRepository.php` | MODIFY | Rozszerzyć `find()` o parametr `UuidInterface $libraryId`. |
| `src/HomeLibrary/Application/Shelf/ShelfBooksCounter.php` | MODIFY | Rozszerzyć `countForShelf()` o parametr `UuidInterface $libraryId`. |

### Verification

- [ ] `bin/console doctrine:migrations:migrate` runs without error
- [ ] `bin/console doctrine:schema:validate` passes
- [ ] `php-cs-fixer` — no violations
- [ ] `phpmd` — no violations
- [ ] Wszystkie zapytania SQL/DQL zawierają filtr `library_id`

### Notes

- Migracja bezpieczna dla pustej bazy (brak danych produkcyjnych — potwierdzone w PRD)
- Dla istniejących danych testowych: migracja przypisuje pierwszy library lub tworzy domyślną
- UNIQUE `shelves(name)` → `shelves(library_id, name)` pozwala na tę samą nazwę półki w różnych bibliotekach
- INDEX na `books.library_id` i `shelves.library_id` dla wydajności zapytań

---

## Phase 3 — Application: Commands, Queries i Handlers z libraryId

**Goal:** Rozszerzyć commands, queries i handlers o `libraryId` aby automatycznie przypisywać i filtrować dane per biblioteka.

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `src/HomeLibrary/Application/Book/Command/CreateBookCommand.php` | MODIFY | Dodać pole `private readonly UuidInterface $libraryId` + getter `libraryId()`. |
| `src/HomeLibrary/Application/Book/CreateBookHandler.php` | MODIFY | 1) Pobrać Library z `LibraryRepository::findById($command->libraryId())`. 2) Walidacja shelf: `findById($shelfId, $command->libraryId())` — jeśli null → ShelfNotFoundException (shelf nie należy do tej biblioteki). 3) Przekazać Library do konstruktora Book. |
| `src/HomeLibrary/Application/Book/DeleteBookHandler.php` | MODIFY | Przyjąć libraryId w DeleteBookCommand. Użyć `findById($id, $libraryId)` — jeśli null → BookNotFoundException. Zapobiega usunięciu książki z innej biblioteki. |
| `src/HomeLibrary/Application/Book/Query/ListBooksQuery.php` | MODIFY (or CREATE) | Dodać pole `UuidInterface $libraryId`. |
| `src/HomeLibrary/Application/Book/Query/ListBooksHandler.php` | MODIFY | Przekazać `$query->libraryId()` do `repository->search()`. |
| `src/HomeLibrary/Application/Shelf/Command/CreateShelfCommand.php` | MODIFY | Dodać pole `private readonly UuidInterface $libraryId` + getter. |
| `src/HomeLibrary/Application/Shelf/CreateShelfHandler.php` | MODIFY | 1) Pobrać Library z `LibraryRepository::findById($command->libraryId())`. 2) Przekazać Library do konstruktora Shelf. |
| `src/HomeLibrary/Application/Shelf/Command/DeleteShelfCommand.php` | MODIFY | Dodać pole `UuidInterface $libraryId` + getter. |
| `src/HomeLibrary/Application/Shelf/DeleteShelfHandler.php` | MODIFY | Użyć `findById($id, $libraryId)` — jeśli null → ShelfNotFoundException. |
| `src/HomeLibrary/Application/Shelf/Query/ListShelvesQuery.php` | MODIFY (or CREATE) | Dodać pole `UuidInterface $libraryId`. |
| `src/HomeLibrary/Application/Shelf/Query/ListShelvesHandler.php` | MODIFY | Przekazać `$query->libraryId()` do `repository->search()` i `countBySearchTerm()`. |
| `src/HomeLibrary/Application/AI/AiRecommendationService.php` | MODIFY | Przekazać `libraryId` do `BookReadRepository::find()`. Wymagane rozszerzenie `GenerateRecommendationsCommand` lub `AcceptRecommendationCommand` o `libraryId`. |
| `src/HomeLibrary/Domain/Library/LibraryRepository.php` | MODIFY | Dodać metodę `findById(UuidInterface $id): ?Library` (jeśli nie istnieje). |
| `src/HomeLibrary/Infrastructure/Persistence/DoctrineLibraryRepository.php` | MODIFY | Zaimplementować `findById()`. |

### Verification

- [ ] Unit tests handlerów przechodzą (po aktualizacji mocków)
- [ ] `php-cs-fixer` — no violations
- [ ] `phpmd` — no violations
- [ ] PHPStan — no errors

### Notes

- Pattern: UI layer wyciąga `$user->library()->id()` i wstawia do Command/Query
- `CreateShelfHandler` — UNIQUE constraint violation catch musi uwzględniać nowy composite unique (library_id, name)
- Library entity resolve w handler (nie w UI) — handler waliduje istnienie biblioteki
- Shelf findById z libraryId → chroni przed przypisaniem książki do cudzej półki

---

## Phase 4 — UI: Przekazywanie libraryId z authenticated User do Commands/Queries

**Goal:** W warstwie UI (API Actions + Web Controllers) wyciągnąć `library_id` z zalogowanego użytkownika i przekazać do commands/queries.

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `src/HomeLibrary/UI/Api/Book/CreateBookAction.php` | MODIFY | Wyciągnąć `$user->library()->id()` z Security token. Przekazać do `CreateBookCommand`. |
| `src/HomeLibrary/UI/Api/Book/DeleteBookAction.php` | MODIFY | Wyciągnąć libraryId. Przekazać do `DeleteBookCommand`. |
| `src/HomeLibrary/UI/Api/Book/ListBooksAction.php` | MODIFY | Wyciągnąć libraryId. Przekazać do `ListBooksQuery`. |
| `src/HomeLibrary/UI/Api/Shelf/CreateShelfAction.php` | MODIFY | Wyciągnąć libraryId. Przekazać do `CreateShelfCommand`. |
| `src/HomeLibrary/UI/Api/Shelf/DeleteShelfAction.php` | MODIFY | Wyciągnąć libraryId. Przekazać do `DeleteShelfCommand`. |
| `src/HomeLibrary/UI/Api/Shelf/ListShelvesAction.php` | MODIFY | Wyciągnąć libraryId. Przekazać do `ListShelvesQuery`. |
| `src/HomeLibrary/UI/Api/AI/GenerateRecommendationsAction.php` | MODIFY | Wyciągnąć libraryId. Przekazać do `GenerateRecommendationsCommand`. |
| `src/HomeLibrary/UI/Api/AI/AcceptRecommendationAction.php` | MODIFY | Wyciągnąć libraryId. Przekazać do `AcceptRecommendationCommand`. |

### Verification

- [ ] Integration tests API przechodzą
- [ ] Brak 500 errors przy braku library context
- [ ] `php-cs-fixer` — no violations
- [ ] `phpmd` — no violations

### Notes

- Pattern do wyciągania usera: `$user = $this->security->getUser()` lub z Request attributes (Symfony Security)
- User MUSI być zalogowany (routes chronione przez firewall) → library zawsze dostępna
- Rozważyć helper trait `LibraryAwareTrait` z metodą `currentLibraryId(): UuidInterface` aby unikać duplikacji — decyzja: inline w Phase 4, refactor do trait jeśli >4 powtórzeń (będzie ~8, więc trait zasadny)
- Web controllers (Twig) korzystają z sub-requestów do API → library context propagowany automatycznie przez sesję

---

## Phase 5 — Testy: Aktualizacja istniejących + nowe testy izolacji

**Goal:** Zaktualizować istniejące testy o nowe parametry (libraryId) oraz dodać dedykowane testy izolacji weryfikujące że user z libraryA nie widzi danych libraryB.

### File Contracts

| File | Action | Contract |
|------|--------|----------|
| `tests/Unit/HomeLibrary/Application/Book/CreateBookHandlerTest.php` | MODIFY | Zaktualizować mock BookRepository, dodać libraryId do commands, zweryfikować że Book otrzymuje Library. |
| `tests/Unit/HomeLibrary/Application/Shelf/CreateShelfHandlerTest.php` | MODIFY | Analogicznie jak Book. |
| `tests/Integration/HomeLibrary/Infrastructure/Persistence/DoctrineBookRepositoryTest.php` | MODIFY | Dodać Library do seedowanych danych. Dodać test: search z libraryA nie zwraca książek libraryB. |
| `tests/Integration/HomeLibrary/Infrastructure/Persistence/DoctrineShelfRepositoryTest.php` | MODIFY (or CREATE) | Dodać Library do seedowanych danych. Dodać test izolacji. |
| `tests/Integration/HomeLibrary/UI/Api/Book/CreateBookApiTest.php` | MODIFY | Zaktualizować payloady, dodać Library do fixture. |
| `tests/Integration/HomeLibrary/UI/Api/Shelf/ListShelvesApiTest.php` | MODIFY | Zaktualizować seed danych o library. Dodać test: user widzi tylko swoje półki. |
| `tests/Integration/HomeLibrary/UI/Api/Book/BookIsolationTest.php` | CREATE | Nowy test: 2 użytkowników w 2 bibliotekach. User A tworzy książkę. User B nie widzi jej w liście. User B nie może jej usunąć (404). |
| `tests/Integration/HomeLibrary/UI/Api/Shelf/ShelfIsolationTest.php` | CREATE | Nowy test: 2 użytkowników w 2 bibliotekach. User A tworzy półkę. User B nie widzi jej. User B nie może jej usunąć (404). |
| `tests/Integration/HomeLibrary/Application/Shelf/DeleteShelfHandlerTest.php` | MODIFY | Dodać Library do seedowanych Shelf. |

### Verification

- [ ] `bin/phpunit` — all tests pass (unit + integration)
- [ ] Nowe testy izolacji pokrywają: list, findById, create, delete
- [ ] Brak false positives — testy nie przechodzą bez filtrowania library_id
- [ ] `php-cs-fixer` — no violations

### Notes

- Testy izolacji powinny weryfikować zarówno brak danych w response (list) jak i 404 przy direct access (findById/delete)
- Istniejące testy tworzące Shelf/Book muszą podać Library w konstruktorze — wymaga refactora fixture helpers
- Helper `createLibrary()` w test base class ułatwi setup
- Testy E2E (Panther) → M4, nie w tym milestone

---

## Dependencies Graph (wewnątrz M3)

```
Phase 1 (Domain)
└── Phase 2 (Infrastructure: migration + repos)
    └── Phase 3 (Application: handlers + commands)
        └── Phase 4 (UI: actions pass libraryId)
            └── Phase 5 (Tests)
```

Każda faza zależy od poprzedniej. Implementacja sekwencyjna.

---

## Risk Mitigation

| Risk | Mitigation |
|------|-----------|
| Pominięcie filtrowania w jednym zapytaniu → wyciek danych | Audyt: grep wszystkich `FROM books` / `FROM shelves` w repo + raw SQL. Testy izolacji jako safety net. |
| Zmiana sygnatur repozytoriów łamie wiele plików naraz | Faza 1-2 najpierw, potem kompilacja i fix po kolei. PHPStan wyłapie brakujące parametry. |
| Unique constraint na shelves.name blokuje migrację | Migracja NAJPIERW dodaje library_id, potem zmienia UNIQUE. |
| Testy flaky przez brak cleanup | Każdy test truncatuje tabele w setUp (istniejący pattern). |

