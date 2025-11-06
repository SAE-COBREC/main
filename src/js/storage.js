/**
 * Module de gestion du storage (cookies + localStorage)
 * Utilisation (module ES) :
 * import storage, { setCookie, getCookie } from './storage.js';
 * setCookie('nom', 'valeur', { days: 7 });
 * setLocal('user', { id: 1, name: 'Elouan' });
 *
 * Fonctions fournies:
 * - Cookies : setCookie, getCookie, editCookie, deleteCookie, listCookies
 * - Local   : setLocal, getLocal, editLocal, removeLocal, clearLocal, listLocal, isLocalStorageAvailable
 */

/**
 * Vérifie si le localStorage est disponible
 * @returns {boolean}
 * @description Tente d'écrire et de supprimer une clé de test dans le localStorage
 * pour vérifier si le localStorage est fonctionnel dans l'environnement actuel.
 * @example
 * // Vérifier si le localStorage est disponible
 * if (isLocalStorageAvailable()) {
 *     console.log("Le localStorage est disponible.");
 * } else {
 *     console.log("Le localStorage n'est pas disponible.");
 * }
 */
function isLocalStorageAvailable() {
  try {
    const key = '__storage_test__';
    localStorage.setItem(key, '1');
    localStorage.removeItem(key);
    return true;
  } catch (e) {
    return false;
  }
}

// ----------------- Cookies -----------------
/**
 * Définit un cookie
 * @param {string} name
 * @param {string} value
 * @param {Object} [options] - { days, path, secure, sameSite, expires: Date }
 * @returns {boolean}
 * @description Définit un cookie avec le nom et la valeur spécifiés.
 * Les options permettent de configurer le chemin, la durée de vie (en jours ou date d'expiration),
 * la sécurité et la politique SameSite du cookie.
 * @example
 * // Définir un cookie "nom" avec la valeur "valeur" qui expire dans 7 jours
 * const success = setCookie("nom", "valeur", { days: 7, path: "/" });
 * if (success) {
 *     console.log("Le cookie a été défini avec succès.");
 * } else {
 *     console.log("Échec de la définition du cookie.");
 * }
 */
function setCookie(name, value, options = {}) {
  if (typeof document === 'undefined') return false;
  if (!name) return false;
  let cookie = encodeURIComponent(String(name)) + '=' + encodeURIComponent(String(value));

  // Gestion de l'expiration
  if (options.expires instanceof Date) {
    cookie += '; expires=' + options.expires.toUTCString();
  } else if (typeof options.days === 'number') {
    const d = new Date();
    d.setTime(d.getTime() + options.days * 24 * 60 * 60 * 1000);
    cookie += '; expires=' + d.toUTCString();
  }

  cookie += '; path=' + (options.path || '/');
  if (options.secure) cookie += '; Secure';
  if (options.sameSite) cookie += '; SameSite=' + options.sameSite; // 'Lax' | 'Strict' | 'None'

  document.cookie = cookie;
  return true;
}

/**
 * Récupère la valeur d'un cookie
 * @param {string} name
 * @returns {string|null}
 * @description Récupère la valeur du cookie spécifié. Retourne null si le cookie n'existe pas.
 * @example
 * // Récupérer la valeur du cookie "nom"
 * const valeur = getCookie("nom");
 * if (valeur !== null) {
 *     console.log("La valeur du cookie est :", valeur);
 * } else {
 *     console.log("Le cookie n'existe pas.");
 * }
 */
function getCookie(name) {
  if (typeof document === 'undefined') return null;
  const encodedName = encodeURIComponent(String(name)) + '=';
  const parts = document.cookie.split(';');
  for (let p of parts) {
    p = p.trim();
    if (p.indexOf(encodedName) === 0) {
      return decodeURIComponent(p.substring(encodedName.length));
    }
  }
  return null;
}

/**
 * Supprime un cookie
 * @param {string} name
 * @param {Object} [options] - { path }
 * @returns {boolean}
 * @description Supprime le cookie spécifié en définissant sa date d'expiration dans le passé.
 * @example
 * // Supprimer le cookie "nom"
 * const success = deleteCookie("nom", { path: "/" });
 * if (success) {
 *     console.log("Le cookie a été supprimé avec succès.");
 * } else {
 *     console.log("Échec de la suppression du cookie.");
 * }
 */
function deleteCookie(name, options = {}) {
  // Pour supprimer, on définit une date passée
  return setCookie(name, '', { path: options.path || '/', expires: new Date(0) });
}

/**
 * Edition (alias de setCookie)
 * @param {string} name
 * @param {string} value
 * @param {Object} [options]
 * @returns {boolean}
 * @description Modifie la valeur d'un cookie existant ou en crée un nouveau si le cookie n'existe pas.
 * @example
 * // Modifier la valeur du cookie "nom"
 * const success = editCookie("nom", "nouvelleValeur", { days: 5 });
 * if (success) {
 *     console.log("Le cookie a été modifié avec succès.");
 * } else {
 *     console.log("Échec de la modification du cookie.");
 * }
 */
function editCookie(name, value, options = {}) {
  return setCookie(name, value, options);
}

/**
 * Retourne tous les cookies sous forme d'objet { name: value }
 * @returns {Object}
 * @description Récupère tous les cookies disponibles et les retourne sous forme d'un objet clé/valeur.
 * @example
 * // Récupérer tous les cookies
 * const allCookies = listCookies();
 * console.log(allCookies);
 * // Exemple de sortie possible :
 * // { nom: "valeur", user: '{"id":1,"name":"Elouan"}' }
 */
function listCookies() {
  if (typeof document === 'undefined') return {};
  const out = {};
  if (!document.cookie) return out;
  const parts = document.cookie.split(';');
  for (let p of parts) {
    const [rawName, ...rest] = p.split('=');
    const name = decodeURIComponent(rawName.trim());
    const value = decodeURIComponent(rest.join('='));
    out[name] = value;
  }
  return out;
}

// ----------------- localStorage -----------------
/**
 * Définit une clé dans localStorage (serialize en JSON si nécessaire)
 * @param {string} key
 * @param {any} value
 * @returns {boolean}
 * @description Définit la valeur associée à la clé spécifiée dans le localStorage.
 * Si la valeur n'est pas une chaîne de caractères, elle est sérialisée en JSON avant d'être stockée.
 * @example
 * // Stocker un objet utilisateur dans le localStorage
 * const user = { id: 1, name: "Elouan" };
 * const success = setLocal("user", user);
 * if (success) {
 *     console.log("Données utilisateur stockées avec succès.");
 * } else {
 *     console.log("Échec du stockage des données utilisateur.");
 * }
 */
function setLocal(key, value) {
  if (!isLocalStorageAvailable()) return false;
  try {
    const v = (typeof value === 'string') ? value : JSON.stringify(value);
    localStorage.setItem(key, v);
    return true;
  } catch (e) {
    console.warn('setLocal error', e);
    return false;
  }
}

/**
 * Récupère et parse une valeur du localStorage
 * @param {string} key
 * @returns {any|null}
 * @description Récupère la valeur associée à la clé spécifiée dans le localStorage.
 * Si la valeur est au format JSON, elle est parsée avant d'être retournée. Sinon, la valeur brute est retournée.
 * @example
 * // Récupérer la valeur associée à la clé "user"
 * const user = getLocal("user");
 * if (user !== null) {
 *     console.log("Données utilisateur récupérées :", user);
 * } else {
 *     console.log("Aucune donnée trouvée pour la clé spécifiée.");
 * }
 */
function getLocal(key) {
  if (!isLocalStorageAvailable()) return null;
  try {
    const raw = localStorage.getItem(key);
    if (raw === null) return null;
    try {
      return JSON.parse(raw);
    } catch (e) {
      return raw; // valeur simple non-JSON
    }
  } catch (e) {
    console.warn('getLocal error', e);
    return null;
  }
}

/**
 * Supprime une clé du localStorage
 * @param {string} key
 * @returns {boolean}
 * @description Supprime la paire clé/valeur associée à la clé spécifiée dans le localStorage.
 * @example
 * // Supprimer la clé "user" du localStorage
 * const success = removeLocal("user");
 * if (success) {
 *     console.log("La clé a été supprimée avec succès.");
 * } else {
 *     console.log("Échec de la suppression de la clé.");
 * }
 */
function removeLocal(key) {
  if (!isLocalStorageAvailable()) return false;
  try {
    localStorage.removeItem(key);
    return true;
  } catch (e) {
    console.warn('removeLocal error', e);
    return false;
  }
}

/**
 * Vide le localStorage
 * @returns {boolean}
 * @description Supprime toutes les clés/valeurs du localStorage.
 * @example
 * // Vider le localStorage
 * const success = clearLocal();
 * if (success) {
 *     console.log("Le localStorage a été vidé avec succès.");
 * } else {
 *     console.log("Échec du vidage du localStorage.");
 * }
 */
function clearLocal() {
  if (!isLocalStorageAvailable()) return false;
  try {
    localStorage.clear();
    return true;
  } catch (e) {
    console.warn('clearLocal error', e);
    return false;
  }
}

/**
 * Edition (alias setLocal)
 * @param {string} key
 * @param {any} value
 * @returns {boolean}
 * @description Modifie la valeur associée à une clé dans le localStorage.
 * @example
 * // Modifier la valeur associée à la clé "user"
 * const success = editLocal("user", { id: 2, name: "Alice" });
 * if (success) {
 *     console.log("La valeur a été modifiée avec succès.");
 * } else {
 *     console.log("Échec de la modification de la valeur.");
 * }
 * // Après modification, récupérer la nouvelle valeur
 * const newUser = getLocal("user");
 * console.log(newUser); // Exemple de sortie : { id: 2, name: "Alice" }
 */
function editLocal(key, value) {
  return setLocal(key, value);
}

/**
 * Liste toutes les clés/valeurs du localStorage (tentative de parse JSON)
 * @returns {Object}
 * @description Retourne un objet contenant toutes les paires clé/valeur du localStorage.
 * @example
 * // Récupérer toutes les données du localStorage
 * const allData = listLocal();
 * console.log(allData);
 * // Exemple de sortie possible :
 * // {
 * //   "user": { "id": 1, "name": "Elouan" },
 * //   "settings": { "theme": "dark", "notifications": true },
 * //   "sessionToken": "abc123xyz"
 * // }
 */
function listLocal() {
  const out = {};
  if (!isLocalStorageAvailable()) return out;
  try {
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      out[key] = getLocal(key);
    }
  } catch (e) {
    console.warn('listLocal error', e);
  }
  return out;
}

// Export
const storage = {
  // cookies
  setCookie,
  getCookie,
  editCookie,
  deleteCookie,
  listCookies,
  // localStorage
  isLocalStorageAvailable,
  setLocal,
  getLocal,
  editLocal,
  removeLocal,
  clearLocal,
  listLocal,
};

export {
  setCookie, // pour définir un cookie
  getCookie, // pour récupérer la valeur d'un cookie
  editCookie, // pour modifier un cookie (alias de setCookie)
  deleteCookie, // pour supprimer un cookie
  listCookies, // pour lister tous les cookies
  isLocalStorageAvailable, // pour vérifier la disponibilité du localStorage
  setLocal, // pour définir une entrée dans le localStorage
  getLocal, // pour récupérer une entrée du localStorage
  editLocal, // pour modifier une entrée du localStorage (alias de setLocal)
  removeLocal, // pour supprimer une entrée du localStorage
  clearLocal, // pour vider le localStorage
  listLocal, // pour lister toutes les entrées du localStorage
};

export default storage;

/**
 * Comment utilisé pour créé, modifier, supprimer des cookies et du localStorage
 *
 * import storage, { setCookie, getCookie, removeCookie, listCookies, 
 *                  setLocal, getLocal, removeLocal, listLocal } from './storage.js';
 *
 * // Cookies
 * setCookie('nom', 'valeur', { days: 7 });
 * const valeur = getCookie('nom');
 * 
 * // LocalStorage
 * setLocal('nom', 'valeur');
 * const valeur = getLocal('nom');
 * 
 * // Supprimer
 * removeLocal('nom');
 * deleteCookie('nom');
 * 
 * // Lister
 * const allCookies = listCookies();
 * const allLocal = listLocal();
 * console.log(allCookies);
 * console.log(allLocal);
 *
 * // Exemple de structure de données
 * {
 *     nom: "valeur",
 *     prenom: "Jean"
 * }
 *
 * // Différents types de valeurs stockables
 * // - Chaînes de caractères
 * // - Nombres
 * // - Objets
 * // - Tableaux
 * // - Valeurs booléennes
 *
 * Différence entre cookies et localStorage
 * -> Cookies : envoyés au serveur à chaque requête HTTP, utilisés pour
 *    la gestion de session, expiration configurable.
 * -> localStorage : stocké localement dans le navigateur, non envoyé
 *    au serveur, persiste jusqu'à suppression manuelle.
 */