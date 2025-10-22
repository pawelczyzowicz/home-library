Frontend - Twig + Symfony

Backend - Symfony z PostgreSQL, doctrine

AI - Komunikacja z modelami przez usługę Openrouter.ai:
- Dostęp do szerokiej gamy modeli (OpenAI, Anthropic, Google i wiele innych), które pozwolą nam znaleźć rozwiązanie zapewniające wysoką efektywność i niskie koszta
- Pozwala na ustawianie limitów finansowych na klucze API

CI/CD i Hosting:
- Github Actions do tworzenia pipeline’ów CI/CD
 
Testy
- Testy jednostkowe: PHPUnit (pokrycie domeny, warstwy aplikacyjnej, test doubles)
- Testy integracyjne: PHPUnit + Doctrine ORM
- Testy e2e: Symfony Panther + Docker + Doctrine Migrations + PostgreSQL (osobna baza testowa)