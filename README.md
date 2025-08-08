# AI-Native Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ai-native/laravel.svg?style=flat-square)](https://packagist.org/packages/ai-native/laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/ai-native/laravel.svg?style=flat-square)](https://packagist.org/packages/ai-native/laravel)
[![License](https://img.shields.io/packagist/l/ai-native/laravel.svg?style=flat-square)](https://packagist.org/packages/ai-native/laravel)

**AI-Native Laravel** est un package Laravel sophistiqué qui génère des APIs complètes à partir de schémas JSON déclaratifs. Spécialement conçu pour le développement assisté par IA et les développeurs, ce package permet de créer des backends Laravel complets grâce à un simple fichier de configuration JSON.

Il intègre un moteur de fusion (merge) intelligent avec sections délimitées permettant des régénérations non destructives : seules les zones marquées sont mises à jour, vos ajouts manuels en dehors des marqueurs restent intacts.

Le package supporte Laravel 10, 11 et 12, et fournit une génération de code complète incluant les modèles, contrôleurs, migrations, factories, seeders, policies, observers, authentification et routes.

## 🚀 Fonctionnalités

### Architecture Avancée
- **Système de génération JSON-vers-Laravel** – 9 générateurs spécialisés
- **ManifestManager intelligent** – Suivi des fichiers avec versioning et nettoyage
- **Parseur de schéma avancé** – Support de configurations complexes
- **Moteur de fusion sectionnelle** – Mise à jour ciblée via marqueurs START/END
- **Marqueurs idempotents** – Chaque bloc généré est clairement délimité
- **Suite de commandes multi-options** – 3 commandes Artisan principales

### Génération Complète
- **Génération automatique** de tous les composants Laravel
- **Relations complexes** (belongsTo, hasMany, belongsToMany, morphTo, polymorphes)
- **Validation automatique** basée sur les règles Laravel intégrées
- **Système d'authentification** complet (Laravel Sanctum/basic)
- **Gestion avancée des fichiers** avec endpoints upload/download automatiques
- **Hooks et observers** personnalisés avec lifecycle events
- **Policies et permissions** par rôle avec autorisation granulaire
- **Cache automatique** configurable avec TTL
- **Support complet des scopes** Eloquent
- **Tables pivot** avec champs additionnels

### Nouvelles Fonctionnalités Avancées
- **Fusion intelligente (--merge)** – Remplacement uniquement des sections marquées
- **Section Markers** – Blocs délimités (ex: `// >>> AI-NATIVE FILLABLE START/END`)
- **Système de versioning** – JSON comme source de vérité
- **Nettoyage automatique** – Suppression des fichiers obsolètes
- **Endpoints de fichiers automatiques** – Upload/download pour chaque champ file/files
- **Auto-configuration environnement** – Configuration .env depuis le JSON
- **Support fichiers multiples** – Gestion intelligente des uploads
- **Manifest & historique** – Traçabilité complète des générations

## 📦 Installation

### Installation via Packagist (Recommandée)

Installez le package via Composer :

```bash
composer require ai-native/laravel
```

### Installation depuis GitHub

Si vous souhaitez installer directement depuis le repository GitHub :

1. **Ajoutez le repository dans votre `composer.json`** :

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Fweac/AI-Native"
        }
    ]
}
```

2. **Installez le package** :

```bash
composer require ai-native/laravel:dev-main
```

### Configuration initiale

Après installation, configurez le package :

```bash
# Publier la configuration et les stubs
php artisan vendor:publish --provider="AiNative\Laravel\AiNativeServiceProvider"
```

Pour une installation complète avec authentification :

```bash
# Installation avec Laravel Sanctum (recommandée)
php artisan ai-native:install --sanctum
```

Ou manuellement :

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

## 🎯 Utilisation Rapide

### 1. Créez votre schéma JSON

```json
{
  "meta": {
    "project": "BlogAPI",
    "version": "1.0.0",
    "auth": {
      "enabled": true,
      "provider": "sanctum"
    }
  },
  "models": {
    "User": {
      "fields": {
        "name": "string|required|max:255",
        "email": "string|email|unique|required",
        "password": "string|required|min:8",
        "avatar": "file:images|nullable|image|max:2048"
      },
      "routes": ["list", "show", "create", "update"],
      "factory": true,
      "seeder": true
    },
    "Post": {
      "fields": {
        "user_id": "foreign:users|required",
        "title": "string|required|max:255",
        "content": "longText|required",
        "status": "enum:draft,published,archived|default:draft",
        "documents": "files:documents|nullable"
      },
      "relations": {
        "author": "belongsTo:User,user_id"
      },
      "routes": ["list", "show", "create", "update", "delete"],
      "scopes": {
        "published": "where:status,published"
      },
      "factory": { "count": 50 },
      "seeder": true
    }
  },
  "storage": {
    "disks": {
      "images": {
        "driver": "local",
        "root": "storage/app/images",
        "url": "/storage/images"
      },
      "documents": {
        "driver": "local",
        "root": "storage/app/documents"
      }
    }
  }
}
```

### 2. Générez votre API

```bash
# Génération complète (mode clean par défaut)
php artisan ai-native:generate schema.json

# Aperçu avant génération (alias: --dry-run)
php artisan ai-native:generate schema.json --preview
php artisan ai-native:generate schema.json --dry-run

# Mode fusion non destructif (sections marquées mises à jour)
php artisan ai-native:generate schema.json --merge

# Génération partielle (ex: seulement modèles et migrations)
php artisan ai-native:generate schema.json --only=models,migrations
```

### 3. Exécutez les migrations

```bash
php artisan migrate
```

### 4. Testez votre API

Votre API est maintenant disponible avec des endpoints comme :
- `GET /api/posts` - Liste des articles
- `POST /api/posts` - Créer un article
- `GET /api/posts/{post}` - Détail d'un article
- `PUT /api/posts/{post}` - Mettre à jour un article
- `POST /api/posts/{post}/upload/documents` - Upload documents
- `GET /api/posts/{post}/download/documents` - Download documents

## 📚 Documentation Complète

### Types de Champs Supportés

| Type | Syntaxe | Description |
|------|---------|-------------|
| string | `"string|required|max:255"` | Chaîne de caractères |
| text | `"text|nullable"` | Texte long |
| longText | `"longText|required"` | Très long texte |
| integer | `"integer|min:0"` | Nombre entier |
| bigInteger | `"bigInteger|unsigned"` | Grand entier |
| boolean | `"boolean|default:false"` | Booléen |
| date | `"date|nullable"` | Date |
| datetime | `"datetime|nullable"` | Date et heure |
| timestamp | `"timestamp|default:now"` | Timestamp |
| json | `"json|nullable"` | Données JSON |
| float | `"float|min:0"` | Nombre décimal |
| uuid | `"uuid|unique"` | UUID unique |
| decimal | `"decimal:10,2|required"` | Nombre décimal précis |
| enum | `"enum:draft,published|default:draft"` | Énumération |
| foreign | `"foreign:users|required"` | Clé étrangère |
| file | `"file:images|nullable|image|max:2048"` | Fichier unique avec upload/download automatique |
| files | `"files:documents|nullable"` | Fichiers multiples avec upload/download automatique |

### Relations Supportées

```json
{
  "relations": {
    "author": "belongsTo:User,user_id",
    "posts": "hasMany:Post,user_id",
    "profile": "hasOne:Profile,user_id",
    "tags": "belongsToMany:Tag,post_tags,post_id,tag_id",
    "comments": "morphMany:Comment,commentable",
    "commentable": "morphTo",
    "images": "morphedByMany:Image,imageable"
  }
}
```

### Gestion Automatique des Fichiers

Pour chaque champ `file:` ou `files:`, le système génère automatiquement :

```json
{
  "User": {
    "fields": {
      "avatar": "file:images|nullable|image|max:2048",
      "documents": "files:documents|nullable"
    }
  }
}
```

**Routes automatiquement créées :**
- `POST /api/users/{user}/upload/avatar` - Upload avatar
- `GET /api/users/{user}/download/avatar` - Download avatar
- `POST /api/users/{user}/upload/documents` - Upload documents
- `GET /api/users/{user}/download/documents` - Download documents

### Scopes Automatiques

```json
{
  "scopes": {
    "published": "where:status,published",
    "recent": "orderBy:created_at,desc",
    "active": "where:is_active,true",
    "byStatus": "where:status,{status}"
  }
}
```

### Système de Hooks

```json
{
  "hooks": {
    "beforeCreate": "hashPassword",
    "afterCreate": [
      {
        "action": "sendMail",
        "template": "welcome",
        "to": "$email",
        "queue": true
      },
      {
        "action": "log",
        "message": "User created: $name"
      }
    ],
    "beforeUpdate": "validateData",
    "afterUpdate": "clearCache"
  }
}
```

### Policies et Permissions

```json
{
  "policies": {
    "viewAny": "role:admin,moderator",
    "view": "owner|role:admin",
    "create": "authenticated",
    "update": "owner|role:admin",
    "delete": "role:admin"
  }
}
```

### Authentification Automatique

Quand `auth.enabled` est activé dans le schéma, le système génère automatiquement :

**Endpoints d'authentification (Sanctum) :**
- `POST /api/register` - Inscription avec token Bearer
- `POST /api/login` - Connexion avec token Bearer  
- `POST /api/logout` - Déconnexion (auth required)
- `GET /api/user` - Utilisateur authentifié (auth required)

```json
{
  "meta": {
    "auth": {
      "enabled": true,
      "provider": "sanctum",
      "routes": ["login", "register", "logout", "user"]
    }
  }
}
```

## 🛠 Commandes Disponibles

### Génération Avancée

```bash
# Génération complète (mode clean par défaut - supprime les fichiers obsolètes)
php artisan ai-native:generate schema.json

# Mode clean explicite
php artisan ai-native:generate schema.json --clean

# Mode fusion avec fichiers existants (merge sectionnel)
php artisan ai-native:generate schema.json --merge

# Aperçu sans créer de fichiers
php artisan ai-native:generate schema.json --preview

# Générer seulement des composants spécifiques
php artisan ai-native:generate schema.json --only=models,migrations
php artisan ai-native:generate schema.json --only=controllers,routes
php artisan ai-native:generate schema.json --only=policies,observers
php artisan ai-native:generate schema.json --only=factories,seeders
```

### Validation

```bash
# Valider un schéma avant génération
php artisan ai-native:validate schema.json
```

### Installation

```bash
# Installation complète avec Sanctum
php artisan ai-native:install --sanctum
```

## 🔧 Architecture et Système de Versioning

### Système de Manifest Intelligent

Chaque génération crée un fichier `.ai-native-manifest.json` qui :
- **Trace tous les fichiers générés** avec métadonnées (hash, taille, timestamp)
- **Détecte automatiquement les changements** de schéma via hash comparison
- **Nettoie intelligemment** les fichiers obsolètes
- **Maintient un historique** complet des générations
- **Permet le rollback** si nécessaire

### JSON comme Source de Vérité

Le système garantit que :
- ✅ **Le JSON prime toujours** sur les fichiers existants
- ✅ **Pas de conflits** - Plus de warnings "file exists"
- ✅ **Traçabilité complète** - Historique des changements
- ✅ **Performance optimisée** - Skip si aucun changement
- ✅ **Cleanup intelligent** - Supprime uniquement les fichiers obsolètes

### Configuration Avancée

Le fichier de configuration `config/ai-native.php` permet de personnaliser :

```php
return [
    'defaults' => [
        'auth' => [
            'enabled' => true,
            'provider' => 'sanctum',
        ],
        'pagination' => [
            'per_page' => 15,
            'max_per_page' => 100,
        ],
        'cache' => [
            'enabled' => true,
            'default_ttl' => 3600,
        ],
        'file_uploads' => [
            'max_size' => 10240, // 10MB
            'allowed_types' => ['image', 'document'],
        ],
    ],
    'manifest' => [
        'enabled' => true,
        'history_limit' => 10,
    ],
    // ...
];
```

## 🔁 Fusion Sectionnelle & Marqueurs

Le mode `--merge` ne réécrit que les zones entourées de marqueurs spéciaux. Tout code personnalisé ajouté hors de ces bornes est conservé.

Exemples de marqueurs utilisés :

```
// >>> AI-NATIVE FILLABLE START
// (contenu géré par le générateur)
// >>> AI-NATIVE FILLABLE END

// >>> AI-NATIVE CONTROLLER HOOKS START
// >>> AI-NATIVE CONTROLLER HOOKS END

// >>> AI-NATIVE POLICY METHODS START
// >>> AI-NATIVE POLICY METHODS END
```

Blocs actuellement mergeables sectionnellement : Models (fillable, casts, relations, scopes), Controllers (hooks & méthodes CRUD), Routes (bloc complet), Policies, Observers, Factories, Seeders, Auth Controller. Les Migrations ne sont pas fusionnées (principe d'immutabilité historique) : on crée une nouvelle migration si le schéma change.

Avantages :
- Régénération sûre et itérative
- Conservation des personnalisations
- Diff ciblé sur blocs structurés

## 🤖 Optimisation pour l'IA

Ce framework est spécifiquement conçu pour le développement assisté par IA :

### Fonctionnalités AI-Friendly
- **Usage minimal de tokens** : Schéma JSON compact réduit les besoins en contexte
- **Patterns standardisés** : Structure cohérente que l'IA peut apprendre et répliquer
- **Auto-génération complète** : Une commande génère l'application Laravel entière
- **Validation intégrée** : Prévient les erreurs communes de code généré par IA
- **Auto-documentation** : Code généré avec commentaires et documentation
- **Contrôle de version** : Suivi des changements et rollback si nécessaire

### Patterns d'usage IA
```
Humain: "Créer une API de gestion de tâches avec utilisateurs, projets, tâches et commentaires"

IA: Je vais créer un schéma JSON complet pour votre API de gestion de tâches :

{
  "meta": {
    "project": "TaskManager", 
    "auth": { "enabled": true, "provider": "sanctum" }
  },
  "models": {
    "User": { /* modèle utilisateur avec authentification */ },
    "Project": { /* modèle projet avec relation utilisateur */ },
    "Task": { /* modèle tâche avec relations projet et utilisateur */ },
    "Comment": { /* modèle commentaire polymorphe */ }
  }
}

Commande : php artisan ai-native:generate task-manager.json
```

## 📋 Exemples Complets

### API E-commerce avec Auto-Configuration

```json
{
  "meta": {
    "project": "EcommerceAPI",
    "version": "1.0.0",
    "auth": {
      "enabled": true,
      "provider": "sanctum",
      "routes": ["login", "register", "logout", "user"]
    },
    "database": {
      "connection": "mysql",
      "host": "localhost",
      "database": "ecommerce_api",
      "username": "root",
      "password": "secret"
    }
  },
  "models": {
    "Product": {
      "fields": {
        "name": "string|required|max:255",
        "price": "decimal:8,2|required|min:0",
        "stock": "integer|default:0|min:0",
        "images": "files:products|nullable",
        "active": "boolean|default:true"
      },
      "relations": {
        "category": "belongsTo:Category,category_id",
        "orders": "belongsToMany:Order,order_product,product_id,order_id"
      },
      "routes": ["list", "show", "create", "update", "delete"],
      "scopes": {
        "active": "where:active,true",
        "inStock": "where:stock,>,0"
      },
      "factory": { "count": 50 },
      "seeder": true
    },
    "Order": {
      "fields": {
        "user_id": "foreign:users|required",
        "status": "enum:pending,paid,shipped,delivered|default:pending",
        "total": "decimal:10,2|required|min:0"
      },
      "relations": {
        "user": "belongsTo:User,user_id",
        "products": "belongsToMany:Product,order_product,order_id,product_id"
      },
      "routes": ["list", "show", "create", "update"],
      "policies": {
        "view": "owner|role:admin",
        "create": "authenticated"
      },
      "factory": { "count": 100 },
      "seeder": true
    }
  },
  "storage": {
    "disks": {
      "products": {
        "driver": "local",
        "root": "storage/app/products",
        "url": "/storage/products"
      }
    }
  }
}
```

**Ce schéma génère automatiquement :**
- ✅ Modèles avec relations et scopes
- ✅ Migrations avec clés étrangères
- ✅ Contrôleurs avec CRUD + upload/download d'images
- ✅ Routes API + authentification complète
- ✅ Factories et seeders avec dépendances
- ✅ Configuration storage et .env
- ✅ Validation et policies

### API Blog avec Tags Polymorphes

```json
{
  "models": {
    "Post": {
      "fields": {
        "title": "string|required|max:255",
        "content": "longText|required",
        "status": "enum:draft,published|default:draft"
      },
      "relations": {
        "tags": "morphedByMany:Tag,taggable"
      }
    },
    "Tag": {
      "fields": {
        "name": "string|required|unique"
      },
      "relations": {
        "posts": "morphedByMany:Post,taggable"
      }
    }
  },
  "pivots": {
    "taggables": {
      "fields": {
        "tag_id": "foreign:tags|required",
        "taggable_id": "integer|required",
        "taggable_type": "string|required",
        "order": "integer|default:0"
      }
    }
  }
}
```

## 📚 Documentation Complète

- **[JSON Keywords](JSON_KEYWORDS.md)** - Documentation complète de tous les mots-clés supportés dans le schéma JSON
- **[GitHub Repository](https://github.com/Fweac/AI-Native)** - Code source et issues

## 📊 Statut des Fonctionnalités

### ✅ **Fonctionnalités Complètement Implémentées**
1. Suppression automatique de welcome.blade.php
2. Seeders avec DatabaseSeeder et ordre dépendances
3. Modes --clean / --merge / --preview (--dry-run)
4. Auth (Sanctum/basic) + routes auto
5. Auto-configuration .env depuis le JSON
6. Manifest + versioning + cleanup intelligent
7. Endpoints upload/download automatiques pour file/files
8. Documentation JSON Keywords complète
9. Fusion sectionnelle multi-générateurs
10. Marqueurs idempotents pour régénération sûre

## 🎯 Notes d'Usage Importantes

- **Mode par défaut** : `--clean` (supprime fichiers obsolètes)
- **Fusion** : Utilisez `--merge` après avoir modifié le JSON pour appliquer seulement les changements nécessaires
- **Aperçu** : `--preview` / `--dry-run` pour voir l'impact sans écrire
- **Migrations** : Pas de merge sectionnel (immuables). Créez de nouvelles migrations manuellement si besoin de refactor.
- **Marqueurs** : Ne pas renommer ni supprimer les lignes START/END si vous voulez conserver la fusion automatique.
- **Personnalisation** : Placez votre code hors des marqueurs.

## 📄 Licence

Ce package est propriétaire. Voir [LICENSE](LICENSE) pour les détails.

## 🙏 Crédits

- Créé pour le développement d'APIs Laravel assisté par IA
- Solution complète pour la génération de code avec gestion d'entreprise
- Basé sur Laravel et ses meilleures pratiques

## 🆘 Support

- [Documentation JSON Keywords](JSON_KEYWORDS.md)
- [GitHub Issues](https://github.com/Fweac/AI-Native/issues)
- [Repository GitHub](https://github.com/Fweac/AI-Native)

---

**AI-Native Laravel** - Générez des APIs Laravel complètes en quelques secondes avec l'IA ! 🚀