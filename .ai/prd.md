# Dokument wymagań produktu (PRD) - HomeLibrary

## 1. Przegląd produktu

HomeLibrary to aplikacja webowa przeznaczona do zarządzania domową kolekcją książek oraz odkrywania nowych, interesujących pozycji przy wsparciu sztucznej inteligencji. System umożliwia organizację książek na wielu regałach z nazwami własnymi, wyszukiwanie i filtrowanie kolekcji oraz otrzymywanie spersonalizowanych rekomendacji książek na podstawie wybranych przez użytkownika tytułów lub autorów.

Aplikacja wspiera wielu użytkowników w ramach jednego gospodarstwa domowego, którzy współdzielą wspólną bibliotekę z równymi uprawnieniami. Głównym celem MVP jest weryfikacja hipotezy, że użytkownicy zaakceptują co najmniej jedną książkę z trzech zaproponowanych przez AI (75% użytkowników testowych).

Grupa docelowa w fazie MVP to znajomi twórcy aplikacji. Projekt powstaje z własnych potrzeb, bez planów monetyzacji w bieżącej fazie.

## 2. Problem użytkownika

Użytkownicy posiadający duże kolekcje książek rozmieszczone na wielu regałach w domu napotykają następujące problemy:

1. Trudność w uporządkowaniu i śledzeniu lokalizacji książek - w domu z wieloma regałami w różnych pomieszczeniach ciężko jest zapamiętać, gdzie konkretna książka się znajduje, co prowadzi do frustracji podczas szukania.

2. Problem z odkrywaniem nowych, wartościowych pozycji - użytkownicy mają trudność w znalezieniu kolejnych interesujących książek dopasowanych do ich aktualnych preferencji czytelniczych. Tradycyjne listy bestsellerów nie uwzględniają indywidualnych gustów.

3. Brak narzędzi do planowania zakupów - nawet gdy użytkownik znajdzie interesującą książkę, brakuje wygodnego sposobu na zanotowanie jej do późniejszego zakupu, co prowadzi do zapominania o tytułach.

4. Zróżnicowane potrzeby czytelnicze - biblioteka domowa zawiera książki z różnych gatunków (fantasy, kryminał, rozwój osobisty, biografie), a użytkownik w różnych momentach potrzebuje różnych typów rekomendacji. Raz szuka kolejnej przygodowej powieści fantasy, innym razem książki o zarządzaniu czasem.

5. Współdzielenie biblioteki w rodzinie - wielu domowników korzysta z tej samej kolekcji, co wymaga wspólnego, aktualnego widoku na zasoby, aby uniknąć kupowania duplikatów lub mylenia lokalizacji książek.

HomeLibrary rozwiązuje te problemy poprzez cyfrowy rejestr książek z przypisaniem do konkretnych regałów, system rekomendacji AI oparty na manualnie wybranych tytułach/autorach (pozwalający na kontekstowe dopasowanie do aktualnego nastroju czytelniczego) oraz specjalny regał "Do zakupu" dla planowanych nabytków.

## 3. Wymagania funkcjonalne

### 3.1 Zarządzanie książkami

3.1.1 Dodawanie książki
- System umożliwia manualne dodanie książki poprzez formularz
- Pola obowiązkowe: tytuł, autor, gatunek (minimum 1, maksimum 3 z predefiniowanej listy)
- Pola opcjonalne: ISBN, liczba stron
- Użytkownik przypisuje książkę do wybranego regału podczas dodawania
- System potwierdza dodanie książki i wyświetla ją na liście

3.1.2 Edycja książki
- System umożliwia edycję wszystkich pól książki (tytuł, autor, gatunek, ISBN, liczba stron, regał)
- Zmiana regału odbywa się poprzez dropdown z listą dostępnych regałów
- System waliduje dane przed zapisem (wymagane pola, format ISBN jeśli podany)
- System potwierdza zapisanie zmian

3.1.3 Usuwanie książki
- System umożliwia usunięcie książki z biblioteki
- System wyświetla potwierdzenie przed usunięciem
- Po usunięciu książka znika z wszystkich widoków i filtrów

3.1.4 Przesuwanie książki między regałami
- System umożliwia zmianę regału poprzez edycję książki i wybór z dropdown
- System aktualizuje lokalizację książki w widoku listy/tabeli
- Zmiana jest natychmiastowo widoczna dla wszystkich użytkowników

### 3.2 System regałów

3.2.1 Tworzenie regału
- Użytkownik może stworzyć nowy regał poprzez podanie nazwy własnej (np. "Salon lewy", "Sypialnia górna półka")
- Nazwa regału musi być unikalna w ramach biblioteki
- System potwierdza utworzenie regału

3.2.2 Regał specjalny "Do zakupu"
- System automatycznie tworzy wbudowany regał "Do zakupu" dla każdej biblioteki
- Regał "Do zakupu" nie może być usunięty
- Regał "Do zakupu" jest wizualnie wyróżniony (ikona koszyka, specjalny kolor)
- Książki zaakceptowane z rekomendacji AI domyślnie trafiają do regału "Do zakupu"

3.2.3 Edycja regału
- System umożliwia zmianę nazwy regału
- Zmiana nazwy regału nie wpływa na przypisane książki

3.2.4 Usuwanie regału
- System umożliwia usunięcie regału (z wyjątkiem regału "Do zakupu")
- Przed usunięciem system sprawdza, czy regał zawiera książki
- Jeśli regał zawiera książki, system wyświetla ostrzeżenie i nie pozwala go usunąć
- System potwierdza usunięcie regału

### 3.3 System gatunków

3.3.1 Lista gatunków
- System oferuje predefiniowaną listę 10-15 gatunków
- Lista obejmuje: Kryminał, Fantasy, Sensacja, Romans, Sci-Fi, Horror, Biografia, Historia, Popularnonaukowa, Literatura piękna, Religia, Thriller, Dramat, Poezja, Komiks
- Każda książka musi mieć przypisany co najmniej 1 gatunek i maksymalnie 3 gatunki
- System nie wspiera hierarchii gatunków (płaska lista)

### 3.4 Wyszukiwanie i filtrowanie

3.4.1 Widok listy/tabeli książek
- System wyświetla wszystkie książki w formacie listy lub tabeli
- Widok zawiera kolumny: tytuł, autor, gatunek(i), regał, opcjonalnie ISBN i liczba stron
- Widok jest responsywny (dostosowuje się do desktop i mobile)

3.4.2 Filtry
- System oferuje filtry: regał (dropdown), gatunek (dropdown lub checkboxy)
- Filtry można łączyć (np. gatunek "Fantasy" + regał "Salon lewy")
- System wyświetla liczbę wyników po zastosowaniu filtrów
- System umożliwia wyczyszczenie wszystkich filtrów jednym kliknięciem

### 3.5 System rekomendacji AI

3.5.1 Wprowadzanie danych wejściowych
- Użytkownik manualnie wprowadza 1 do kilku tytułów książek wraz nazwiskiem autora lub pojedyncze nazwisko autora jako punkt odniesienia
- System oferuje formularz z polami tekstowymi dla tytułów/autorów
- System waliduje, że podano co najmniej 1 tytuł lub autora

3.5.2 Generowanie rekomendacji
- System wysyła zapytanie do AI provider (OpenAI GPT-4o-mini lub Anthropic Claude) z kontekstem podanych tytułów/autorów
- Prompt zawiera instrukcje dla AI: analiza podanych pozycji, zwrócenie 3 rekomendacji z uwzględnieniem gatunków, wykluczenie tytułów już obecnych w bibliotece
- System przetwarza odpowiedź AI i wyświetla 3 zaproponowane książki

3.5.3 Wyświetlanie propozycji
- System wyświetla 3 książki z danymi: tytuł, autor, krótkie uzasadnienie rekomendacji
- Każda propozycja ma przyciski: "Akceptuj" i "Odrzuć"
- System wyróżnia propozycje wizualnie (np. karty z ikoną AI)

3.5.4 Akceptacja propozycji
- Użytkownik klika "Akceptuj" przy wybranej książce
- System automatycznie dodaje książkę do regału "Do zakupu"
- System rejestruje event "book_accepted" w bazie danych (timestamp, user_id, book_id, source='ai_recommendation')
- System potwierdza dodanie książki

3.5.5 Odrzucenie propozycji
- Użytkownik klika "Odrzuć" przy wybranej książce
- Książka znika z listy propozycji (bez dodawania do biblioteki)
- System nie rejestruje eventi dla odrzuconych książek

3.5.6 Tracking rekomendacji
- System rejestruje event "ai_recommendation_generated" przy każdym wygenerowaniu zestawu rekomendacji
- Dane eventi: timestamp, user_id, input_titles (podane tytuły/autorzy), recommended_book_ids (ID 3 zaproponowanych książek)

### 3.6 Zarządzanie użytkownikami

3.6.1 Rejestracja
- System oferuje formularz rejestracji z polami: email, hasło, potwierdzenie hasła, imię (opcjonalnie)
- System waliduje: format email, siłę hasła (minimum 8 znaków), zgodność haseł
- System tworzy konto użytkownika i automatycznie loguje po rejestracji

3.6.2 Logowanie
- System oferuje formularz logowania z polami: email, hasło
- System waliduje dane logowania
- W przypadku błędnych danych system wyświetla komunikat błędu
- Po poprawnym zalogowaniu system przekierowuje do dashboardu/listy książek
- System utrzymuje sesję użytkownika

3.6.3 Wylogowanie
- Użytkownik może wylogować się z systemu
- System kończy sesję i przekierowuje do strony logowania

3.6.4 Model uprawnień
- Wszyscy użytkownikami w ramach jednego gospodarstwa domowego mają równe uprawnienia
- Każdy użytkownik może: dodawać, edytować, usuwać książki; tworzyć, edytować, usuwać regały; korzystać z rekomendacji AI
- System nie implementuje ról użytkowników w MVP

3.6.5 Wspólna biblioteka
- Wszyscy użytkownicy gospodarstwa domowego widzą tę samą kolekcję książek i regałów
- Zmiany dokonane przez jednego użytkownika są natychmiastowo widoczne dla innych (po odświeżeniu strony)

### 3.7 Analytics i tracking

3.7.1 Baza danych eventi
- System przechowuje eventy w bazie PostgreSQL w dedykowanych tabelach
- Tabela ai_recommendation_events: id, timestamp, user_id, input_titles (JSON), recommended_book_ids (JSON), accepted_book_ids (JSON, aktualizowane przy akceptacji)

3.7.2 Kalkulacja metryki sukcesu
- System umożliwia zapytanie SQL do wyliczenia % użytkowników, którzy zaakceptowali ≥1 książkę z rekomendacji AI
- Formuła: (liczba unikalnych id z ai_recommendation_events gdzie accepted_book_ids zawiera ≥1 element) / (liczba unikalnych id w ai_recommendation_events) × 100%

## 4. Granice produktu

### 4.1 Funkcjonalności NIE wchodzące w zakres MVP

4.1.1 Osobiste listy życzeń użytkowników
- Brak możliwości tworzenia prywatnych list życzeń dla poszczególnych użytkowników
- Brak możliwości dodawania książek z AI do list życzeń
- W MVP wszyscy użytkownicy współdzielą wspólny regał "Do zakupu"

4.1.2 Wiele bibliotek (gospodarstw domowych)
- System nie wspiera wielu bibliotek dla różnych domów
- Użytkownik nie może być przypisany do kilku gospodarstw domowych, gdyż nie ma podziału na gospodarstwa domowe
- MVP zakłada jedną wspólną bibliotekę dla wszsytkich użytkowników

4.1.3 Lista planowanych książek do przeczytania z blokowaniem
- Brak osobnych list "do przeczytania" dla poszczególnych domowników
- Brak mechanizmu blokowania dodania książki, gdy jest na liście innego użytkownika
- Brak systemu rezerwacji książek

4.1.4 Automatyczne rekomendacje bazujące na całej bibliotece
- System nie generuje automatycznie rekomendacji na podstawie analizy całej biblioteki użytkownika
- Rekomendacje AI wymagają manualnego podania tytułów/autorów jako punktu odniesienia
- Brak proaktywnych powiadomień o nowych rekomendacjach

4.1.5 System ocen i recenzji
- Użytkownicy nie mogą oceniać książek (gwiazdki, punkty)
- Brak możliwości pisania recenzji lub notatek o książkach
- Brak historii przeczytanych książek z datami

4.1.6 Import z zewnętrznych API
- Brak automatycznego pobierania danych książek z Google Books, OpenLibrary, Goodreads
- Wszystkie dane książek muszą być wprowadzane manualnie
- Brak automatycznego wypełniania pól na podstawie ISBN

4.1.7 Drag-and-drop
- Przesuwanie książek między regałami odbywa się wyłącznie poprzez edycję i dropdown
- Brak interfejsu drag-and-drop dla reorganizacji

4.1.8 Aplikacje mobilne native
- Brak aplikacji iOS i Android
- Aplikacja dostępna wyłącznie jako webapp przez przeglądarkę (responsywna)

4.1.9 Monetyzacja i affiliate links
- Brak linków affiliate do sklepów z książkami
- Brak integracji z platformami sprzedażowymi
- Brak planów monetyzacji w MVP

4.1.10 Zaawansowane analytics użytkownika
- Brak dashboardu z statystykami czytelniczymi
- Brak wykresów wzrostu biblioteki, najczęściej czytanych gatunków, itp.
- Tracking ograniczony wyłącznie do miary sukcesu MVP

### 4.2 Ograniczenia techniczne

4.2.1 Skalowanie
- MVP zoptymalizowane dla grup testowych (znajomi twórcy), nie dla tysięcy użytkowników
- Brak zaawansowanej infrastruktury cache i load balancing w pierwszej wersji

4.2.2 Integracje
- Brak integracji z czytnikami e-booków (Kindle, Kobo)
- Brak synchronizacji z innymi systemami katalogowania książek

4.2.3 Backup i recovery
- Podstawowy backup bazy danych, brak zaawansowanych mechanizmów disaster recovery

## 5. Historyjki użytkowników

### 5.1 Zarządzanie kontem i uwierzytelnianie

US-001: Rejestracja nowego konta

Jako nowy użytkownik chcę zarejestrować się w systemie HomeLibrary, aby móc tworzyć i zarządzać moją domową biblioteką.

Kryteria akceptacji:
- System wyświetla formularz rejestracji z polami: email, hasło, potwierdzenie hasła
- System waliduje format email (zawiera @, domenę)
- System waliduje, że hasło ma minimum 8 znaków
- System waliduje, że hasło i potwierdzenie hasła są identyczne
- Jeśli email już istnieje w bazie, system wyświetla komunikat "Ten email jest już zarejestrowany"
- Po poprawnej rejestracji system tworzy konto, automatycznie loguje użytkownika i przekierowuje do dashboardu
- System wyświetla komunikat powitalny po pierwszym zalogowaniu

US-002: Logowanie do systemu

Jako zarejestrowany użytkownik chcę zalogować się do systemu, aby uzyskać dostęp do mojej biblioteki.

Kryteria akceptacji:
- System wyświetla formularz logowania z polami: email, hasło
- System wyświetla link "Nie masz konta? Zarejestruj się"
- Po wpisaniu poprawnych danych system loguje użytkownika i przekierowuje do dashboardu/listy książek
- Po wpisaniu błędnych danych system wyświetla komunikat "Nieprawidłowy email lub hasło"
- System utrzymuje sesję użytkownika (nie wymaga ponownego logowania przy kolejnych wizytach przez określony czas)
- System nie wyświetla, czy błąd dotyczy email czy hasła (bezpieczeństwo)

US-003: Wylogowanie z systemu

Jako zalogowany użytkownik chcę móc wylogować się z systemu, aby zabezpieczyć moje konto na współdzielonym urządzeniu.

Kryteria akceptacji:
- System wyświetla przycisk/link "Wyloguj" w nagłówku lub menu użytkownika
- Po kliknięciu "Wyloguj" system kończy sesję użytkownika
- System przekierowuje do strony logowania
- System wyświetla komunikat "Wylogowano pomyślnie"
- Po wylogowaniu próba dostępu do chronionych stron przekierowuje do logowania

### 5.2 Zarządzanie książkami

US-004: Dodanie nowej książki do biblioteki

Jako użytkownik chcę dodać nową książkę do mojej biblioteki, podając tytuł, autora i gatunek oraz przypisać ją do konkretnego regału, aby wiedzieć gdzie fizycznie się znajduje.

Kryteria akceptacji:
- System wyświetla przycisk "Dodaj książkę" w dashboardzie/liście książek
- System wyświetla formularz z polami: tytuł (wymagane), autor (wymagane), gatunek (wymagane, wybór 1-3 z listy), regał (wymagane, dropdown), ISBN (opcjonalne), liczba stron (opcjonalne)
- Lista gatunków zawiera: Kryminał, Fantasy, Sensacja, Romans, Sci-Fi, Horror, Biografia, Historia, Popularnonaukowa, Literatura piękna, Religia, Thriller, Dramat, Poezja, Komiks
- Użytkownik może wybrać minimum 1 i maksimum 3 gatunki (checkboxy lub multi-select)
- Dropdown regałów zawiera wszystkie utworzone regały włącznie z "Do zakupu"
- System waliduje, że wszystkie wymagane pola są wypełnione
- System waliduje format ISBN, jeśli podany (10 lub 13 cyfr)
- System waliduje, że liczba stron jest liczbą całkowitą dodatnią, jeśli podana
- Po kliknięciu "Zapisz" system dodaje książkę do bazy danych
- System wyświetla komunikat "Książka została dodana"
- System przekierowuje do listy książek, gdzie nowa książka jest widoczna

US-005: Edycja danych książki

Jako użytkownik chcę edytować dane książki (w tym zmienić tytuł, autora, gatunek, ISBN, liczbę stron), aby poprawić błędy lub zaktualizować informacje.

Kryteria akceptacji:
- System wyświetla przycisk "Edytuj" przy każdej książce w widoku listy lub szczegółów
- System wyświetla formularz edycji z wypełnionymi aktualnymi danymi książki
- Użytkownik może zmienić dowolne pole: tytuł, autor, gatunek (1-3), regał, ISBN, liczba stron
- System waliduje dane analogicznie jak przy dodawaniu
- Po kliknięciu "Zapisz" system aktualizuje dane książki w bazie
- System wyświetla komunikat "Zmiany zostały zapisane"
- Zaktualizowane dane są natychmiastowo widoczne na liście książek

US-006: Przeniesienie książki na inny regał

Jako użytkownik chcę przenieść książkę z jednego regału na drugi, edytując jej dane, aby odzwierciedlić zmiany w fizycznej organizacji moich książek.

Kryteria akceptacji:
- System umożliwia edycję książki (przycisk "Edytuj")
- W formularzu edycji pole "Regał" wyświetla dropdown z listą wszystkich regałów
- Aktualny regał jest preselektowany w dropdown
- Użytkownik wybiera nowy regał z listy
- Po kliknięciu "Zapisz" system aktualizuje przypisanie książki do nowego regału
- System wyświetla komunikat "Książka została przeniesiona do regału [nazwa]"
- Na liście książek książka wyświetla się z nowym regałem
- Filtrowanie po regale pokazuje książkę w nowej lokalizacji

US-007: Usunięcie książki z biblioteki

Jako użytkownik chcę usunąć książkę z biblioteki, gdy już jej nie posiadam lub została dodana przez pomyłkę.

Kryteria akceptacji:
- System wyświetla przycisk "Usuń" przy każdej książce w widoku listy lub szczegółów
- Po kliknięciu "Usuń" system wyświetla modal z potwierdzeniem: "Czy na pewno chcesz usunąć książkę [tytuł]? Tej operacji nie można cofnąć."
- Modal zawiera przyciski "Anuluj" i "Usuń"
- Po kliknięciu "Anuluj" modal zamyka się bez usuwania
- Po kliknięciu "Usuń" system usuwa książkę z bazy danych
- System wyświetla komunikat "Książka została usunięta"
- Książka znika z listy książek i wszystkich widoków
- Jeśli książka była dodana przez AI i zaakceptowana, usunięcie nie wpływa na dane analytics

US-008: Wyświetlenie szczegółów książki

Jako użytkownik chcę zobaczyć wszystkie szczegóły książki, aby sprawdzić pełne informacje o niej.

Kryteria akceptacji:
- System wyświetla link/przycisk do szczegółów przy każdej książce (np. tytuł jest klikalny)
- Po kliknięciu system wyświetla widok szczegółów książki
- Widok zawiera wszystkie dane: tytuł, autor, gatunek(i), regał, ISBN (jeśli podany), liczba stron (jeśli podana)
- Widok zawiera przyciski: "Edytuj", "Usuń", "Powrót do listy"
- W widoku szczegółów wyświetla się informacja, jeśli książka została dodana z rekomendacji AI

### 5.3 Zarządzanie regałami

US-009: Utworzenie nowego regału

Jako użytkownik chcę utworzyć nowy regał z nazwą własną (np. "Salon lewy", "Sypialnia górna półka"), aby organizować książki według fizycznych lokalizacji w domu.

Kryteria akceptacji:
- System wyświetla przycisk "Dodaj regał" w sekcji zarządzania regałami lub w dropdown podczas dodawania książki
- System wyświetla formularz z polem: nazwa regału (wymagane)
- Użytkownik wpisuje nazwę własną regału
- System waliduje, że nazwa nie jest pusta
- System waliduje, że nazwa jest unikalna (nie istnieje już taki regał)
- Po kliknięciu "Zapisz" system tworzy nowy regał
- System wyświetla komunikat "Regał został utworzony"
- Nowy regał pojawia się w dropdown podczas dodawania/edycji książek
- Nowy regał pojawia się w filtrach

US-011: Usunięcie regału

Jako użytkownik chcę usunąć niepotrzebny regał, gdy już go nie używam.

Kryteria akceptacji:
- System wyświetla przycisk "Usuń" przy każdym regale (oprócz "Do zakupu")
- Regał "Do zakupu" nie ma przycisku "Usuń" i nie może być usunięty
- Po kliknięciu "Usuń" system sprawdza, czy regał zawiera książki
- Jeśli regał jest pusty, system wyświetla modal: "Czy na pewno chcesz usunąć regał [nazwa]?"
- Jeśli regał zawiera książki, system wyświetla modal: "Regał [nazwa] zawiera [liczba] książek. Wybierz docelowy regał do przeniesienia książek lub potwierdź usunięcie książek."
- Modal zawiera: dropdown z innymi regałami, opcję "Usuń również książki", przyciski "Anuluj" i "Usuń regał"
- Po wybraniu docelowego regału i kliknięciu "Usuń regał" system przenosi książki i usuwa regał
- Po wybraniu "Usuń również książki" system usuwa regał wraz z książkami
- System wyświetla komunikat "Regał został usunięty"
- Regał znika z dropdown, filtrów i listy regałów

US-012: Wyświetlenie specjalnego regału "Do zakupu"

Jako użytkownik chcę mieć dostęp do specjalnego regału "Do zakupu", gdzie automatycznie trafiają książki zaakceptowane z rekomendacji AI, aby planować przyszłe zakupy.

Kryteria akceptacji:
- System automatycznie tworzy regał "Do zakupu" podczas tworzenia biblioteki (przy rejestracji)
- Regał "Do zakupu" jest widoczny w dropdown podczas dodawania/edycji książek
- Regał "Do zakupu" jest widoczny w filtrach
- Regał "Do zakupu" jest wizualnie wyróżniony ikoną koszyka i specjalnym kolorem (np. zielony lub niebieski)
- Regał "Do zakupu" nie ma przycisku "Usuń"
- Regał "Do zakupu" może być wybrany manualnie przez użytkownika podczas dodawania książki
- Książki zaakceptowane z rekomendacji AI automatycznie trafiają do regału "Do zakupu"

### 5.4 Wyszukiwanie i filtrowanie

US-013: Wyświetlenie listy wszystkich książek

Jako użytkownik chcę zobaczyć listę wszystkich moich książek w formie tabeli, aby mieć przegląd całej kolekcji.

Kryteria akceptacji:
- System wyświetla listę/tabelę wszystkich książek w bibliotece po zalogowaniu (dashboard)
- Tabela zawiera kolumny: tytuł, autor, gatunek(i), regał
- Opcjonalnie tabela zawiera kolumny: ISBN, liczba stron (jeśli są wypełnione)
- Tabela jest responsywna (dostosowuje się do szerokości ekranu, na mobile może być listą kart)
- Każdy wiersz zawiera przyciski: "Szczegóły", "Edytuj", "Usuń"
- Jeśli biblioteka jest pusta, system wyświetla komunikat "Twoja biblioteka jest pusta. Dodaj pierwszą książkę!"
- System wyświetla liczbę wszystkich książek (np. "Książki (47)")

US-014: Wyszukiwanie książek po tytule lub autorze

Jako użytkownik z dużą biblioteką chcę szybko znaleźć książkę po tytule lub autorze, aby nie przewijać długiej listy.

Kryteria akceptacji:
- System wyświetla pole wyszukiwania nad listą książek z placeholderem "Szukaj po tytule lub autorze"
- Wyszukiwanie działa w trybie live search (filtrowanie podczas wpisywania bez konieczności klikania)
- System filtruje książki, których tytuł LUB autor zawiera wpisany tekst (case-insensitive)
- Wyniki wyszukiwania aktualizują się natychmiastowo podczas wpisywania
- System wyświetla liczbę znalezionych wyników (np. "Znaleziono: 5")
- Jeśli brak wyników, system wyświetla "Nie znaleziono książek pasujących do '[wpisany tekst]'"
- Użytkownik może wyczyścić pole wyszukiwania przyciskiem "X" w polu lub usuwając tekst
- Po wyczyszczeniu wyświetlają się wszystkie książki

US-015: Filtrowanie książek po regale

Jako użytkownik chcę filtrować książki po regale, aby zobaczyć tylko książki z konkretnej lokalizacji.

Kryteria akceptacji:
- System wyświetla dropdown "Filtruj po regale" nad listą książek
- Dropdown zawiera opcje: "Wszystkie regały" oraz listę wszystkich regałów (włącznie z "Do zakupu")
- Domyślnie wybrany jest "Wszystkie regały"
- Po wyborze regału system wyświetla tylko książki z tego regału
- System wyświetla liczbę wyfiltrowanych wyników (np. "Książki w regale 'Salon lewy': 12")
- Filtr można wyczyścić wybierając "Wszystkie regały"
- Filtr działa łącznie z wyszukiwaniem (jeśli aktywne) - AND logic

US-016: Filtrowanie książek po gatunku

Jako użytkownik chcę filtrować książki po gatunku, aby znaleźć wszystkie książki z konkretnej kategorii.

Kryteria akceptacji:
- System wyświetla dropdown lub listę checkboxów "Filtruj po gatunku" nad listą książek
- Lista zawiera wszystkie gatunki: Kryminał, Fantasy, Sensacja, Romans, Sci-Fi, Horror, Biografia, Historia, Popularnonaukowa, Literatura piękna, Religia, Thriller, Dramat, Poezja, Komiks
- Użytkownik może wybrać jeden lub więcej gatunków
- System wyświetla książki, które mają KTÓRĄKOLWIEK z wybranych gatunków (OR logic)
- System wyświetla liczbę wyfiltrowanych wyników
- Filtr można wyczyścić odznaczając wszystkie gatunki lub przyciskiem "Wyczyść"
- Filtr działa łącznie z wyszukiwaniem i filtrem regału (AND logic między różnymi filtrami)

US-017: Czyszczenie wszystkich filtrów

Jako użytkownik chcę wyczyścić wszystkie aktywne filtry i wyszukiwanie jednym kliknięciem, aby szybko wrócić do widoku pełnej biblioteki.

Kryteria akceptacji:
- System wyświetla przycisk "Wyczyść wszystkie filtry" nad listą książek
- Przycisk jest aktywny tylko gdy jest aktywne wyszukiwanie LUB jakikolwiek filtr
- Po kliknięciu przycisku system:
  - Czyści pole wyszukiwania
  - Resetuje filtr regału do "Wszystkie regały"
  - Odznacza wszystkie gatunki
- System wyświetla pełną listę wszystkich książek
- System wyświetla komunikat "Filtry wyczyszczone" lub aktualizuje liczbę wyników

### 5.5 System rekomendacji AI

US-018: Wprowadzenie tytułów/autorów do rekomendacji

Jako użytkownik chcę wprowadzić tytuły książek wraz z autorami lub nazwiska autorów, które lubię, aby otrzymać podobne rekomendacje od AI.

Kryteria akceptacji:
- System wyświetla przycisk "Poproś AI o rekomendacje" w menu lub dashboardzie
- Po kliknięciu system wyświetla formularz z polami tekstowymi dla tytułów wraz z autorami /autorów
- Formularz zawiera co najmniej 3 pola tekstowe z możliwością dodania więcej ("Dodaj kolejne pole")
- Pola mają placeholdery: "Wpisz tytuł książki lub autora"
- System waliduje, że podano co najmniej 1 tytuł/autor (przynajmniej jedno pole wypełnione)
- System wyświetla przycisk "Generuj rekomendacje"
- Formularz zawiera przykłady: "Np. 'Wiedźmin Andrzej Sapkowski' lub 'Andrzej Sapkowski'"

US-019: Wygenerowanie rekomendacji AI

Jako użytkownik chcę otrzymać 3 propozycje książek od AI podobnych do podanych przeze mnie tytułów/autorów, aby znaleźć kolejne interesujące pozycje do przeczytania.

Kryteria akceptacji:
- Po kliknięciu "Generuj rekomendacje" system wyświetla loader/spinner z tekstem "AI analizuje Twoje preferencje..."
- System wysyła zapytanie do AI provider (OpenAI GPT-4o-mini lub Anthropic Claude) z promptem zawierającym:
  - Podane tytuły/autorów
  - Instrukcję zwrócenia 3 rekomendacji
  - Instrukcję dopasowania gatunków
  - Listę tytułów już obecnych w bibliotece użytkownika (do wykluczenia)
- System przetwarza odpowiedź AI i parsuje dane: tytuł, autor, uzasadnienie dla każdej z 3 książek
- System wyświetla 3 karty z rekomendacjami, każda zawiera:
  - Tytuł
  - Autor
  - Krótkie uzasadnienie (1-2 zdania)
  - Przycisk "Akceptuj"
  - Przycisk "Odrzuć"
- System rejestruje event "ai_recommendation_generated" w bazie (timestamp, user_id, input_titles, recommended_book_ids)
- Jeśli wystąpi błąd API, system wyświetla komunikat "Nie udało się wygenerować rekomendacji. Spróbuj ponownie."

US-020: Akceptacja książki z rekomendacji AI

Jako użytkownik chcę zaakceptować zaproponowaną przez AI książkę i dodać ją do regału "Do zakupu", aby zapamiętać ją na przyszłość.

Kryteria akceptacji:
- System wyświetla przycisk "Akceptuj" przy każdej z 3 zaproponowanych książek
- Po kliknięciu "Akceptuj" system:
  - Tworzy nową książkę w bazie danych z danymi: tytuł, autor z rekomendacji AI
  - Przypisuje książkę do regału "Do zakupu"
  - Dodaje metadane: source='ai_recommendation', recommendation_id
  - Rejestruje event "book_accepted" (timestamp, user_id, book_id, source='ai_recommendation')
- System wyświetla komunikat "Książka '[tytuł]' została dodana do regału 'Do zakupu'"
- Zaakceptowana książka znika z listy propozycji lub jest wizualnie oznaczona jako zaakceptowana
- Książka jest natychmiastowo widoczna w regale "Do zakupu" i na liście wszystkich książek
- Użytkownik może zaakceptować więcej niż jedną książkę z zestawu

US-021: Odrzucenie książki z rekomendacji AI

Jako użytkownik chcę odrzucić zaproponowaną przez AI książkę, gdy nie jestem nią zainteresowany.

Kryteria akceptacji:
- System wyświetla przycisk "Odrzuć" przy każdej z 3 zaproponowanych książek
- Po kliknięciu "Odrzuć" książka znika z listy propozycji (fadeout animation)
- System NIE dodaje książki do biblioteki
- System NIE rejestruje eventi dla odrzucenia (tracking tylko akceptacji)
- Użytkownik może odrzucić wszystkie 3 książki
- Po odrzuceniu wszystkich książek system wyświetla opcję "Wygeneruj nowe rekomendacje" lub powrotu do formularza

US-022: Ponowne wygenerowanie rekomendacji

Jako użytkownik chcę wygenerować nowe rekomendacje z innymi tytułami/autorami, gdy poprzednie propozycje mi nie odpowiadają.

Kryteria akceptacji:
- System wyświetla przycisk "Wygeneruj nowe rekomendacje" po wyświetleniu propozycji AI
- Po kliknięciu system wraca do formularza wprowadzania tytułów/autorów
- Formularz jest pusty lub zawiera poprzednie dane (do wyboru w projekcie UX)
- Użytkownik może wprowadzić nowe tytuły/autorów i ponownie kliknąć "Generuj rekomendacje"
- System generuje nowy zestaw 3 propozycji (mogą być inne niż poprzednio, nawet dla tych samych inputów)
- Każde wygenerowanie rekomendacji rejestruje nowy event "ai_recommendation_generated"

### 5.6 Współdzielenie biblioteki

US-023: Wyświetlanie zmian dokonanych przez innych użytkowników

Jako użytkownik współdzielący bibliotekę z domownikami chcę widzieć zmiany dokonane przez innych użytkowników, aby być na bieżąco z aktualnymstanem kolekcji.

Kryteria akceptacji:
- Wszyscy zalogowani użytkownicy w jednym gospodarstwie domowym widzą tę samą bibliotekę (te same książki i regały)
- Gdy użytkownik A doda/edytuje/usunie książkę, zmiany są widoczne dla użytkownika B po odświeżeniu strony
- Gdy użytkownik A utworzy/edytuje/usunie regał, zmiany są widoczne dla użytkownika B po odświeżeniu strony
- System nie wyświetla informacji, który użytkownik dokonał zmiany (brak historii zmian w MVP)
- Brak automatycznego odświeżania/WebSocket (wymaga ręcznego odświeżenia strony F5)

US-024: Dodawanie książek przez wielu użytkowników bez konfliktów

Jako użytkownik współdzielący bibliotekę chcę móc dodawać książki niezależnie od innych domowników, bez blokowania ani konfliktów.

Kryteria akceptacji:
- Wielu użytkowników może jednocześnie dodawać książki do biblioteki
- System nie blokuje możliwości dodania tej samej książki przez różnych użytkowników (brak sprawdzania duplikatów w MVP)
- Jeśli dwóch użytkowników doda tę samą książkę, obie kopie będą w bibliotece (użytkownik może później usunąć duplikat)
- Każdy użytkownik ma pełne uprawnienia CRUD na wszystkich książkach (także dodanych przez innych)

### 5.7 Scenariusze skrajne i błędy

US-025: Obsługa błędów połączenia z AI provider

Jako użytkownik chcę otrzymać zrozumiały komunikat, gdy system nie może wygenerować rekomendacji z powodu problemów technicznych.

Kryteria akceptacji:
- Jeśli zapytanie do AI provider timeout (>30s), system wyświetla "Generowanie rekomendacji trwa dłużej niż zwykle. Spróbuj ponownie."
- Jeśli AI provider zwraca błąd (429 rate limit, 500 server error, brak API key), system wyświetla "Nie udało się połączyć z systemem rekomendacji. Spróbuj ponownie później."
- Jeśli AI zwróci niepoprawny format odpowiedzi, system wyświetla "Wystąpił błąd podczas przetwarzania rekomendacji. Spróbuj ponownie."
- System nie crashuje i pozwala użytkownikowi wrócić do formularza lub dashboardu
- Błędy są logowane po stronie backend dla debugowania

US-026: Próba edycji nieistniejącej książki

Jako użytkownik chcę otrzymać komunikat o błędzie, gdy próbuję edytować książkę, która została usunięta przez innego użytkownika.

Kryteria akceptacji:
- Jeśli użytkownik A wyświetla listę książek, a użytkownik B usuwa książkę, następnie użytkownik A klika "Edytuj" na usuniętej książce
- System sprawdza, czy książka istnieje przed wyświetleniem formularza
- Jeśli książka nie istnieje, system wyświetla komunikat "Ta książka została usunięta" i przekierowuje do listy książek
- Lista książek odświeża się pokazując aktualny stan

US-027: Próba usunięcia regału "Do zakupu"

Jako użytkownik chcę być uniemożliwione usunięcie specjalnego regału "Do zakupu", aby zachować integralność systemu rekomendacji.

Kryteria akceptacji:
- Regał "Do zakupu" nie wyświetla przycisku "Usuń" w interfejsie
- Jeśli użytkownik spróbuje usunąć regał "Do zakupu" bezpośrednim zapytaniem do API (ominięcie UI), system zwraca błąd 403 Forbidden
- System wyświetla komunikat "Regał 'Do zakupu' jest regałem systemowym i nie może być usunięty"
- Regał "Do zakupu" pozostaje w bazie danych

US-028: Obsługa pustej biblioteki

Jako nowy użytkownik z pustą biblioteką chcę zobaczyć pomocne komunikaty zachęcające do dodania pierwszych książek.

Kryteria akceptacji:
- Gdy użytkownik po rejestracji wchodzi do dashboardu z pustą biblioteką, system wyświetla:
  - Komunikat "Twoja biblioteka jest pusta. Dodaj pierwszą książkę!"
  - Przycisk "Dodaj książkę" (call-to-action, wyróżniony wizualnie)
  - Opcjonalnie: krótką instrukcję lub grafika onboardingowa
- Funkcje wyszukiwania i filtrowania są nieaktywne lub ukryte (brak sensu dla pustej biblioteki)
- Funkcja rekomendacji AI jest dostępna, ale system może wyświetlić wskazówkę "Dodaj kilka książek, aby AI mogło wykluczyć duplikaty z rekomendacji"

US-029: Walidacja długości pól tekstowych

Jako użytkownik chcę otrzymać komunikaty walidacyjne, gdy wprowadzam zbyt długie lub zbyt krótkie dane.

Kryteria akceptacji:
- Pola tekstowe mają ograniczenia długości:
  - Tytuł książki: 1-255 znaków
  - Autor książki: 1-255 znaków
  - Nazwa regału: 1-100 znaków
  - ISBN: 10 lub 13 cyfr
  - Liczba stron: liczba całkowita 1-50000
- Jeśli użytkownik przekroczy limit, system wyświetla komunikat "Pole '[nazwa]' może zawierać maksymalnie [X] znaków"
- Jeśli pole wymagane jest puste, system wyświetla "Pole '[nazwa]' jest wymagane"
- Walidacja działa po stronie frontend (natychmiastowa) i backend (przed zapisem)

US-030: Próba utworzenia regału z duplikowaną nazwą

Jako użytkownik chcę otrzymać komunikat o błędzie, gdy próbuję utworzyć regał z nazwą, która już istnieje.

Kryteria akceptacji:
- Gdy użytkownik wprowadza nazwę regału identyczną z istniejącym regałem (case-insensitive)
- System waliduje unikalność nazwy przed zapisem
- System wyświetla komunikat "Regał o nazwie '[nazwa]' już istnieje. Wybierz inną nazwę."
- Formularz pozostaje otwarty z wypełnionymi danymi, aby użytkownik mógł zmienić nazwę
- Walidacja działa po stronie backend (frontend może opcjonalnie pokazać live feedback)

### 5.8 Analytics i tracking

US-031: Automatyczne rejestrowanie eventi rekomendacji AI

Jako administrator produktu chcę, aby system automatycznie rejestrował eventy związane z rekomendacjami AI, aby móc analizować skuteczność funkcji.

Kryteria akceptacji:
- System automatycznie zapisuje event "ai_recommendation_generated" w bazie danych przy każdym wygenerowaniu rekomendacji
- Event zawiera: id, timestamp, user_id, input_titles (JSON: lista podanych tytułów/autorów), recommended_book_ids (JSON: tablica 3 ID zaproponowanych książek)
- System automatycznie zapisuje event "book_accepted" w bazie danych przy każdej akceptacji książki z AI
- Event zawiera: id, timestamp, user_id, book_id, source='ai_recommendation', recommendation_id (referencja do eventi "ai_recommendation_generated")
- Eventy są zapisywane synchronicznie (przed zwróceniem odpowiedzi użytkownikowi)
- W przypadku błędu zapisu eventi system loguje błąd, ale NIE przerywa procesu (użytkownik nie widzi błędu)

US-032: Kalkulacja metryki sukcesu MVP

Jako administrator produktu chcę móc wyliczyć % zaakceptowanych rekomendacji, przynajmniej 1 książkę z rekomendacji AI, aby zmierzyć sukces MVP.

Kryteria akceptacji:
- System udostępnia zapytanie SQL lub endpoint API do wyliczenia metryki
- Metryka: (liczba unikalnych id, którzy mają co najmniej 1 event "book_accepted" z source='ai_recommendation') / (liczba unikalnych id, którzy mają co najmniej 1 event "ai_recommendation_generated") × 100%
- Wynik wyświetla się jako procent zaokrąglony do jednego miejsca po przecinku
- System pozwala filtrować metrykę po okresie czasu (np. ostatni miesiąc, od początku)
- Przykładowy rezultat: "Sukces MVP: 78.5% (22 z 28 użytkowników zaakceptowało ≥1 rekomendację)"

## 6. Metryki sukcesu

### 6.1 Główne kryterium sukcesu MVP

75% zarekomendowanych zestawów przez AI jest akceptowanych - przynajmniej jedną książkę z zestawu 3 książek zaproponowanego przez AI.

Definicja akceptacji: użytkownik kliknął przycisk "Akceptuj" przy książce z rekomendacji AI, co skutkowało dodaniem książki do regału "Do zakupu".

Sposób pomiaru:
- Tracking w bazie danych PostgreSQL poprzez eventy:
  - ai_recommendation_generated: rejestrowany przy każdym wygenerowaniu zestawu rekomendacji
  - book_accepted: rejestrowany przy każdej akceptacji książki z AI
- Kalkulacja:
  - Licznik: liczba unikalnych rekomendacji (ai_recommendation_events.id), którzy mają co najmniej 1 rekord book_accepted z source='ai_recommendation'
  - Mianownik: liczba unikalnych rekomendacji (ai_recommendation_events.id), którzy mają co najmniej 1 rekord ai_recommendation_generated
  - Formuła: (Licznik / Mianownik) × 100%
- Próg sukcesu: ≥ 75%

Próg sukcesu oznacza, że hipoteza produktowa (AI może efektywnie rekomendować książki dopasowane do preferencji użytkownika) została zweryfikowana pozytywnie, i warto rozwijać produkt w kierunku pełniejszej wersji.
