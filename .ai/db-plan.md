## 1. Lista tabel (kolumny, typy danych, ograniczenia)

### users
- id: uuid PK, NOT NULL, DEFAULT gen_random_uuid()
- email: varchar(255) NOT NULL
  - CHECK (char_length(email) BETWEEN 3 AND 255)
  - Unikalność case-insensitive (indeks na lower(email))
- password_hash: text NOT NULL
- created_at: timestamptz NOT NULL DEFAULT now()
- updated_at: timestamptz NOT NULL DEFAULT now()

### shelves
- id: uuid PK, NOT NULL, DEFAULT gen_random_uuid()
- name: varchar(50) NOT NULL
- is_system: boolean NOT NULL DEFAULT false
- created_at: timestamptz NOT NULL DEFAULT now()
- updated_at: timestamptz NOT NULL DEFAULT now()
- Ochrona: trigger blokujący UPDATE/DELETE dla wierszy z is_system = true

### books
- id: uuid PK, NOT NULL, DEFAULT gen_random_uuid()
- title: varchar(255) NOT NULL
- author: varchar(255) NOT NULL
- isbn: text NULL
  - CHECK (isbn ~ '^\d{10}(\d{3})?$')
- page_count: integer NULL
- source: book_source_enum NOT NULL DEFAULT 'manual'  // enum: manual, ai_recommendation
- recommendation_id: bigint NULL REFERENCES ai_recommendation_events(id)
- shelf_id: uuid NOT NULL REFERENCES shelves(id)
- created_at: timestamptz NOT NULL DEFAULT now()
- updated_at: timestamptz NOT NULL DEFAULT now()

### genres
- id: integer PK, NOT NULL  // stała lista seedowana
- name: varchar(100) NOT NULL, indeks

### book_genre (relacja M:N)
- book_id: uuid NOT NULL REFERENCES books(id)
- genre_id: integer NOT NULL REFERENCES genres(id)
- UNIQUE (book_id, genre_id)

### ai_recommendation_events
- id: int PK
- created_at: timestamptz NOT NULL DEFAULT now()
- user_id: uuid NULL REFERENCES users(id)
- input_titles: jsonb NOT NULL  // lista tytułów/autorów wejściowych
- recommended_book_ids: jsonb NOT NULL DEFAULT '[]'::jsonb  // lista 3 pozycji (ID lub obiekty)
- accepted_book_ids: jsonb NOT NULL DEFAULT '[]'::jsonb

### system_tables (opcjonalne, techniczne)
- schema_migrations: zgodnie z narzędziem migracji (zarządzane przez framework)

## 2. Relacje między tabelami
- users (1) — (N) ai_recommendation_events via user_id
- shelves (1) — (N) books via shelf_id
- books (N) — (M) genres via book_genre
- ai_recommendation_events (1) — (N) books via books.recommendation_id (opcjonalnie, tylko dla zaakceptowanych z AI)

Kardynalności:
- shelves → books: 1:N
- books ↔ genres: M:N (łącznik `book_genre`)
- users → ai_recommendation_events: 1:N
- ai_recommendation_events → books: 1:N (tylko zaakceptowane; nie wszystkie rekomendacje muszą być przyjęte)

Zachowanie przy usuwaniu:
- shelves.id → books.shelf_id: ON DELETE RESTRICT (nie można usunąć regału z książkami)
- books.id → book_genre.book_id: ON DELETE CASCADE (sprząta powiązania)
- genres.id → book_genre.genre_id: ON DELETE RESTRICT (blokada usunięcia gatunku używanego)
- ai_recommendation_events.id → books.recommendation_id: ON DELETE SET NULL

## 3. Indeksy
- users: UNIQUE INDEX ON lower(email)
- shelves: UNIQUE INDEX ON lower(name)
- genres: UNIQUE INDEX ON lower(name)
- books: INDEX ON shelf_id
- book_genre: INDEX ON book_id; INDEX ON genre_id; UNIQUE (book_id, genre_id)
- ai_recommendation_events:
  - INDEX ON (user_id, created_at)
  - GIN INDEX ON recommended_book_ids
  - GIN INDEX ON accepted_book_ids
- Wyszukiwanie (opcjonalnie, dla ILIKE):
  - pg_trgm GIN/GIN_trgm na books.title, books.author

## 4. Dodatkowe uwagi i decyzje projektowe
- Wymagane rozszerzenia:
  - `pgcrypto` (gen_random_uuid())
  - `pg_trgm` (opcjonalnie dla szybkiego ILIKE po title/author)
- Typy i enumy:
```sql
CREATE TYPE book_source_enum AS ENUM ('manual', 'ai_recommendation');
```
- Automatyczna aktualizacja `updated_at`:
```sql
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS trigger AS $$
BEGIN
  NEW.updated_at := now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_users_updated_at
BEFORE UPDATE ON users
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER trg_shelves_updated_at
BEFORE UPDATE ON shelves
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER trg_books_updated_at
BEFORE UPDATE ON books
FOR EACH ROW EXECUTE FUNCTION set_updated_at();
```
- Blokada zmian/usunięcia regału systemowego:
```sql
CREATE OR REPLACE FUNCTION prevent_system_shelf_change()
RETURNS trigger AS $$
BEGIN
  IF TG_OP IN ('UPDATE','DELETE') AND OLD.is_system THEN
    RAISE EXCEPTION 'Shelf is system-protected and cannot be %', lower(TG_OP);
  END IF;
  RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_shelves_protect_system_u
BEFORE UPDATE ON shelves
FOR EACH ROW EXECUTE FUNCTION prevent_system_shelf_change();

CREATE TRIGGER trg_shelves_protect_system_d
BEFORE DELETE ON shelves
FOR EACH ROW EXECUTE FUNCTION prevent_system_shelf_change();
```
- Unikalność case-insensitive:
```sql
CREATE UNIQUE INDEX ux_users_email_ci ON users (lower(email));
CREATE UNIQUE INDEX ux_shelves_name_ci ON shelves (lower(name));
CREATE UNIQUE INDEX ux_genres_name_ci ON genres (name);
```
- Inicjalizacja danych:
  - `genres`: seed 10–15 pozycji z PRD (kryminał, fantasy, sensacja, romans, sci‑fi, horror, biografia, historia, popularnonaukowa, literatura piękna, religia, thriller, dramat, poezja, komiks).
  - `shelves`: utwórz systemowy regał „Do zakupu” w migracji inicjalnej:
```sql
INSERT INTO shelves (id, name, is_system) VALUES (gen_random_uuid(), 'Do zakupu', true)
ON CONFLICT DO NOTHING;
```
- Zgodność z PRD:
  - Długości pól i walidacje (title, author, shelf.name, isbn, page_count) odzwierciedlone w CHECK.
  - Duplikaty książek dozwolone (MVP).
  - Tracking AI: jeden rekord w `ai_recommendation_events` na wygenerowany zestaw; przy akceptacji książek:
    - aplikacja uzupełnia `books.source = 'ai_recommendation'` i `books.recommendation_id = ai_recommendation_events.id`
    - aktualizuje `accepted_book_ids` (append) w odpowiadającym rekordzie `ai_recommendation_events`.