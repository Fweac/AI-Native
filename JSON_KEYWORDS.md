# JSON Keywords Documentation

Cette documentation liste tous les mots-clés supportés dans le schéma JSON d'ai-native/laravel pour aider les développeurs à créer des schémas JSON valides.

## 📋 Structure Globale

```json
{
  "meta": { ... },
  "models": { ... },
  "pivots": { ... },
  "storage": { ... },
  "custom": { ... }
}
```

---

## 🔧 Section META

Configuration globale du projet et de l'environnement Laravel.

### Mots-clés de base

| Mot-clé | Type | Description | Exemple |
|---------|------|-------------|---------|
| `project` | string | Nom du projet | `"TaskManager"` |
| `version` | string | Version du projet | `"1.0.0"` |
| `description` | string | Description du projet | `"API de gestion des tâches"` |

### Section `app`

Configuration de l'application Laravel.

```json
{
  "meta": {
    "app": {
      "name": "Mon Application",
      "url": "https://api.example.com",
      "timezone": "Europe/Paris"
    }
  }
}
```

| Mot-clé | Type | Description | Défaut |
|---------|------|-------------|--------|
| `name` | string | Nom de l'application | Valeur de `project` |
| `url` | string | URL de l'application | `http://localhost` |
| `timezone` | string | Fuseau horaire | `UTC` |

### Section `auth`

Configuration de l'authentification.

```json
{
  "meta": {
    "auth": {
      "enabled": true,
      "provider": "sanctum",
      "guards": ["web", "api"],
      "routes": ["login", "register", "logout", "user"]
    }
  }
}
```

| Mot-clé | Type | Description | Valeurs possibles |
|---------|------|-------------|-------------------|
| `enabled` | boolean | Active l'authentification | `true`, `false` |
| `provider` | string | Provider d'authentification | `"sanctum"`, `"basic"` |
| `guards` | array | Guards Laravel | `["web", "api"]` |
| `routes` | array | Routes auth à générer | `["login", "register", "logout", "user", "refresh"]` |

### Section `database`

Configuration de la base de données.

```json
{
  "meta": {
    "database": {
      "connection": "mysql",
      "host": "localhost",
      "database": "mon_app",
      "username": "root",
      "password": "secret",
      "charset": "utf8mb4",
      "collation": "utf8mb4_unicode_ci"
    }
  }
}
```

| Mot-clé | Description | Valeurs possibles |
|---------|-------------|-------------------|
| `connection` | Type de base de données | `"mysql"`, `"pgsql"`, `"sqlite"`, `"sqlsrv"` |
| `host` | Hôte de la BDD | `"localhost"`, IP, hostname |
| `database` | Nom de la base | Nom de votre base |
| `username` | Utilisateur BDD | Nom d'utilisateur |
| `password` | Mot de passe BDD | Mot de passe |
| `charset` | Jeu de caractères | `"utf8mb4"` (recommandé) |
| `collation` | Collation | `"utf8mb4_unicode_ci"` |

### Autres sections META

```json
{
  "meta": {
    "mail": {
      "mailer": "smtp",
      "host": "mailhog",
      "port": 1025,
      "from": {
        "address": "noreply@example.com",
        "name": "Mon App"
      }
    },
    "cache": {
      "default": "redis",
      "stores": {
        "redis": { "driver": "redis" }
      }
    },
    "queues": {
      "default": "database",
      "connections": {
        "database": { "driver": "database" }
      }
    },
    "cors": {
      "paths": ["api/*"],
      "allowed_methods": ["GET", "POST", "PUT", "DELETE"],
      "allowed_origins": ["*"]
    },
    "middlewares": ["auth:sanctum", "throttle:60,1"]
  }
}
```

---

## 🏗️ Section MODELS

Définition des modèles Eloquent et de leurs propriétés.

### Structure d'un modèle

```json
{
  "models": {
    "User": {
      "table": "users",
      "fields": { ... },
      "relations": { ... },
      "routes": [ ... ],
      "scopes": { ... },
      "policies": { ... },
      "hooks": { ... },
      "observers": { ... },
      "filters": { ... },
      "factory": { ... },
      "seeder": true,
      "cache": { ... }
    }
  }
}
```

### Mots-clés du modèle

| Mot-clé | Type | Description | Exemple |
|---------|------|-------------|---------|
| `table` | string | Nom personnalisé de la table | `"custom_users"` |
| `fields` | object | Définition des champs | Voir section suivante |
| `relations` | object | Relations entre modèles | Voir section relations |
| `routes` | array | Routes à générer | `["list", "show", "create", "update", "delete"]` |
| `scopes` | object | Scopes Eloquent | Voir section scopes |
| `policies` | object | Policies d'autorisation | Voir section policies |
| `hooks` | object | Hooks lifecycle | Voir section hooks |
| `observers` | object | Observers de modèle | Voir section observers |
| `filters` | object | Filtres de requête | Voir section filters |
| `factory` | object/boolean | Configuration factory | `true` ou `{ "count": 50 }` |
| `seeder` | boolean | Générer un seeder | `true`, `false` |
| `cache` | object | Configuration cache | `{ "enabled": true, "ttl": 3600 }` |

---

## 📝 Types de Champs (FIELDS)

Définition des champs avec validation Laravel intégrée.

### Format général

```
"nom_champ": "type|validation|validation"
```

### Types de base

| Type | Migration Laravel | Validation | Exemple |
|------|------------------|------------|---------|
| `string` | `string()` | Chaîne de caractères | `"name": "string|required|max:255"` |
| `text` | `text()` | Texte long | `"description": "text|nullable"` |
| `longText` | `longText()` | Très long texte | `"content": "longText|required"` |
| `integer` | `integer()` | Nombre entier | `"age": "integer|min:0|max:120"` |
| `bigInteger` | `bigInteger()` | Grand entier | `"user_id": "bigInteger|unsigned"` |
| `boolean` | `boolean()` | Booléen | `"active": "boolean|default:true"` |
| `date` | `date()` | Date | `"birth_date": "date|nullable"` |
| `datetime` | `dateTime()` | Date et heure | `"created_at": "datetime|nullable"` |
| `timestamp` | `timestamp()` | Timestamp | `"last_login": "timestamp|default:now"` |
| `json` | `json()` | Données JSON | `"settings": "json|nullable"` |
| `float` | `float()` | Nombre décimal | `"price": "float|min:0"` |
| `uuid` | `uuid()` | UUID | `"uuid": "uuid|unique"` |

### Types avancés

| Type | Format | Description | Exemple |
|------|--------|-------------|---------|
| `decimal` | `decimal:precision,scale` | Décimal précis | `"price": "decimal:10,2|required"` |
| `enum` | `enum:val1,val2,val3` | Énumération | `"status": "enum:draft,published,archived|default:draft"` |
| `foreign` | `foreign:table` | Clé étrangère | `"user_id": "foreign:users|required"` |
| `file` | `file:disk` | Fichier unique | `"avatar": "file:images|nullable|image|max:2048"` |
| `files` | `files:disk` | Fichiers multiples | `"documents": "files:documents|nullable"` |

### Règles de validation

Toutes les règles de validation Laravel sont supportées :

- **Required/Optional** : `required`, `nullable`, `sometimes`
- **Strings** : `max:255`, `min:3`, `email`, `url`, `regex:pattern`
- **Numbers** : `min:0`, `max:100`, `between:1,10`
- **Files** : `image`, `max:2048`, `mimes:pdf,doc,docx`
- **Unique** : `unique`, `unique:table,column`
- **Dates** : `date`, `after:tomorrow`, `before:2025-01-01`

---

## 🔗 Relations (RELATIONS)

Définition des relations entre modèles.

### Format général

```
"nom_relation": "type:ModelCible[,paramètres]"
```

### Types de relations

| Type | Format | Description | Exemple |
|------|--------|-------------|---------|
| `belongsTo` | `belongsTo:Model,foreign_key` | Appartient à | `"user": "belongsTo:User,user_id"` |
| `hasOne` | `hasOne:Model,foreign_key` | A un | `"profile": "hasOne:Profile,user_id"` |
| `hasMany` | `hasMany:Model,foreign_key` | A plusieurs | `"posts": "hasMany:Post,user_id"` |
| `belongsToMany` | `belongsToMany:Model,table,key1,key2` | Plusieurs à plusieurs | `"roles": "belongsToMany:Role,user_roles,user_id,role_id"` |
| `morphTo` | `morphTo` | Polymorphe (appartient) | `"commentable": "morphTo"` |
| `morphMany` | `morphMany:Model,name` | Polymorphe (plusieurs) | `"comments": "morphMany:Comment,commentable"` |
| `morphedByMany` | `morphedByMany:Model,name` | Polymorphe plusieurs-à-plusieurs | `"tags": "morphedByMany:Tag,taggable"` |

### Exemples complets

```json
{
  "models": {
    "Post": {
      "relations": {
        "author": "belongsTo:User,user_id",
        "category": "belongsTo:Category",
        "comments": "hasMany:Comment,post_id",
        "tags": "belongsToMany:Tag,post_tags,post_id,tag_id",
        "images": "morphMany:Image,imageable"
      }
    }
  }
}
```

---

## 🛣️ Routes (ROUTES)

Routes à générer pour le modèle.

### Routes disponibles

| Route | Méthode HTTP | Endpoint | Action contrôleur |
|-------|--------------|----------|-------------------|
| `list` / `index` | GET | `/models` | `index()` |
| `show` | GET | `/models/{id}` | `show()` |
| `create` / `store` | POST | `/models` | `store()` |
| `update` | PUT | `/models/{id}` | `update()` |
| `delete` / `destroy` | DELETE | `/models/{id}` | `destroy()` |

### Exemples

```json
{
  "User": {
    "routes": ["list", "show", "create", "update"]
  },
  "Post": {
    "routes": ["index", "show", "store", "update", "destroy"]
  }
}
```

### Routes de fichiers (automatiques)

Si un modèle a des champs `file` ou `files`, ces routes sont automatiquement ajoutées :

- `POST /models/{id}/upload/{field}` - Upload de fichier(s)
- `GET /models/{id}/download/{field}` - Download de fichier(s)

---

## 🔍 Scopes (SCOPES)

Scopes Eloquent pour filtrer les requêtes.

### Format

```json
{
  "scopes": {
    "nom_scope": "type:paramètres"
  }
}
```

### Types de scopes

| Type | Format | Exemple | Code généré |
|------|--------|---------|-------------|
| `where` | `where:field,value` | `"active": "where:is_active,true"` | `->where('is_active', true)` |
| `orderBy` | `orderBy:field,direction` | `"recent": "orderBy:created_at,desc"` | `->orderBy('created_at', 'desc')` |
| `whereNull` | `whereNull:field` | `"deleted": "whereNull:deleted_at"` | `->whereNull('deleted_at')` |

### Utilisation

```php
// Dans le code généré
User::active()->recent()->get();
```

---

## 🔐 Policies (POLICIES)

Policies d'autorisation pour contrôler l'accès aux ressources.

### Actions disponibles

| Action | Description | Appelée pour |
|--------|-------------|--------------|
| `viewAny` | Voir la liste | Route `index` |
| `view` | Voir un élément | Route `show` |
| `create` | Créer | Route `store` |
| `update` | Modifier | Route `update` |
| `delete` | Supprimer | Route `destroy` |

### Règles d'autorisation

| Règle | Description | Exemple |
|-------|-------------|---------|
| `role:admin` | Nécessite le rôle admin | `"delete": "role:admin"` |
| `role:admin,moderator` | Un des rôles requis | `"update": "role:admin,moderator"` |
| `authenticated` | Utilisateur connecté | `"create": "authenticated"` |
| `owner` | Propriétaire de la ressource | `"update": "owner"` |
| `owner|role:admin` | Propriétaire OU admin | `"view": "owner|role:admin"` |

### Exemple complet

```json
{
  "User": {
    "policies": {
      "viewAny": "role:admin,moderator",
      "view": "owner|role:admin",
      "create": "authenticated",
      "update": "owner|role:admin",
      "delete": "role:admin"
    }
  }
}
```

---

## 🎣 Hooks (HOOKS)

Hooks de cycle de vie pour exécuter du code lors d'événements.

### Événements disponibles

| Hook | Moment d'exécution |
|------|--------------------|
| `beforeCreate` | Avant création |
| `afterCreate` | Après création |
| `beforeUpdate` | Avant modification |
| `afterUpdate` | Après modification |
| `beforeDelete` | Avant suppression |
| `afterDelete` | Après suppression |

### Types de hooks

#### Hook simple (string)
```json
{
  "hooks": {
    "beforeCreate": "hashPassword"
  }
}
```

#### Hook complexe (objet)
```json
{
  "hooks": {
    "afterCreate": {
      "action": "sendMail",
      "template": "welcome",
      "to": "$email",
      "queue": true
    }
  }
}
```

#### Hooks multiples (array)
```json
{
  "hooks": {
    "afterCreate": [
      {
        "action": "sendMail",
        "template": "welcome",
        "to": "$email"
      },
      {
        "action": "log",
        "message": "User created: $name"
      }
    ]
  }
}
```

---

## 👀 Observers (OBSERVERS)

Observers de modèle pour réagir aux événements Eloquent.

```json
{
  "observers": {
    "created": "logUserCreation",
    "updated": "updateSearchIndex",
    "deleted": "cleanupUserData",
    "restored": "restoreUserData"
  }
}
```

### Événements Eloquent

- `created`, `creating`
- `updated`, `updating`
- `deleted`, `deleting`
- `restored`, `restoring`
- `saved`, `saving`

---

## 🔍 Filters (FILTERS)

Filtres de requête pour l'API.

```json
{
  "filters": {
    "search": "where:name,like,%{search}%",
    "status": "where:status,{status}",
    "date_range": "whereBetween:created_at,{start},{end}",
    "category": "whereHas:category,name,{category}"
  }
}
```

### Utilisation API

```
GET /api/users?search=john&status=active&date_range[start]=2024-01-01&date_range[end]=2024-12-31
```

---

## 🏭 Factory & Seeder

### Factory

```json
{
  "factory": {
    "count": 50,
    "states": ["published", "draft"]
  }
}
```

ou simplement : `"factory": true`

### Seeder

```json
{
  "seeder": true
}
```

Génère automatiquement le seeder avec l'ordre de dépendances respecté.

---

## 📦 Section PIVOTS

Tables pivot personnalisées avec champs additionnels.

```json
{
  "pivots": {
    "user_roles": {
      "fields": {
        "user_id": "foreign:users|required",
        "role_id": "foreign:roles|required",
        "assigned_at": "timestamp|default:now",
        "assigned_by": "foreign:users|nullable"
      }
    }
  }
}
```

---

## 💾 Section STORAGE

Configuration des disques de stockage.

```json
{
  "storage": {
    "disks": {
      "images": {
        "driver": "local",
        "root": "storage/app/images",
        "url": "/storage/images"
      },
      "documents": {
        "driver": "s3",
        "bucket": "app-documents",
        "region": "eu-west-1"
      }
    }
  }
}
```

---

## ⚙️ Section CUSTOM

Routes personnalisées non générées automatiquement.

```json
{
  "custom": {
    "routes": [
      {
        "method": "GET",
        "uri": "/api/dashboard/stats",
        "controller": "DashboardController@stats",
        "middleware": ["auth:sanctum"]
      },
      {
        "method": "POST",
        "uri": "/api/bulk-import",
        "controller": "ImportController@bulk",
        "middleware": ["auth:sanctum", "admin"]
      }
    ]
  }
}
```

---

## 🎯 Exemple Complet

Voici un exemple JSON utilisant la plupart des mots-clés disponibles :

```json
{
  "meta": {
    "project": "BlogAPI",
    "version": "1.0.0",
    "auth": {
      "enabled": true,
      "provider": "sanctum"
    },
    "database": {
      "connection": "mysql",
      "host": "localhost",
      "database": "blog_api"
    }
  },
  "models": {
    "User": {
      "fields": {
        "name": "string|required|max:255",
        "email": "string|email|unique|required",
        "password": "string|required|min:8",
        "avatar": "file:images|nullable|image|max:2048",
        "active": "boolean|default:true"
      },
      "relations": {
        "posts": "hasMany:Post,user_id"
      },
      "routes": ["list", "show", "create", "update"],
      "scopes": {
        "active": "where:active,true"
      },
      "policies": {
        "view": "owner|role:admin"
      },
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
        "author": "belongsTo:User,user_id",
        "tags": "belongsToMany:Tag,post_tags,post_id,tag_id"
      },
      "routes": ["list", "show", "create", "update", "delete"],
      "filters": {
        "search": "where:title,like,%{search}%",
        "status": "where:status,{status}"
      },
      "hooks": {
        "afterCreate": {
          "action": "sendMail",
          "template": "post-created"
        }
      },
      "factory": { "count": 100 },
      "seeder": true
    }
  },
  "storage": {
    "disks": {
      "images": {
        "driver": "local",
        "root": "storage/app/images"
      },
      "documents": {
        "driver": "local", 
        "root": "storage/app/documents"
      }
    }
  }
}
```

Ce schéma génère automatiquement :
- ✅ Modèles User et Post avec relations
- ✅ Migrations avec clés étrangères
- ✅ Contrôleurs avec CRUD + upload/download
- ✅ Routes API + authentification
- ✅ Factories et seeders
- ✅ Configuration storage
- ✅ Validation et policies

---

## 📚 Ressources

- **Documentation Laravel** : https://laravel.com/docs
- **Validation Rules** : https://laravel.com/docs/validation#available-validation-rules
- **Eloquent Relations** : https://laravel.com/docs/eloquent-relationships
- **Laravel Sanctum** : https://laravel.com/docs/sanctum