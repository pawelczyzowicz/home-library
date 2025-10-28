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

## 2. Relacje między tabelami
- users (1) — (N) ai_recommendation_events via user_id
- shelves (1) — (N) books via shelf_id
- books (N) — (M) genres via book_genre
- ai_recommendation_events (1) — (N) books via books.recommendation_id

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

## 4. Inicjalizacja danych:
  - `genres`: seed 10–15 pozycji z PRD (kryminał, fantasy, sensacja, romans, sci‑fi, horror, biografia, historia, popularnonaukowa, literatura piękna, religia, thriller, dramat, poezja, komiks).
  - `shelves`: utwórz systemowy regał „Do zakupu” w migracji inicjalnej:
```sql
INSERT INTO shelves (id, name, is_system) VALUES (gen_random_uuid(), 'Do zakupu', true)
ON CONFLICT DO NOTHING;
```
