/**
 * URL de base de l'API Laravel.
 *
 * URL relative : les appels passent par le proxy `/api` du serveur web
 * (nginx du conteneur frontend, qui relaie vers l'API Laravel). La boutique
 * fonctionne donc depuis n'importe quel hôte — localhost, adresse réseau ou
 * nom de domaine — sans configuration CORS supplémentaire.
 *
 * En développement hors Docker (`ng serve`), le même chemin est relayé par
 * `proxy.conf.json` vers l'API exposée sur le port 80.
 */
export const API_BASE_URL = '/api';
