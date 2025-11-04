import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
app.register('shelves', () => import('./controllers/shelves_controller.js'));
app.register('books-autosubmit', () => import('./controllers/books_autosubmit_controller.js'));
app.register('books-clear', () => import('./controllers/books_clear_controller.js'));
app.register('confirm-delete', () => import('./controllers/confirm_delete_controller.js'));
app.register('books-table', () => import('./controllers/books_table_controller.js'));
app.register('books-create', () => import('./controllers/books_create_controller.js'));
