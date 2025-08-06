# AI-Native Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ai-native/laravel.svg?style=flat-square)](https://packagist.org/packages/ai-native/laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/ai-native/laravel.svg?style=flat-square)](https://packagist.org/packages/ai-native/laravel)
[![License](https://img.shields.io/packagist/l/ai-native/laravel.svg?style=flat-square)](https://packagist.org/packages/ai-native/laravel)

**AI-Native Laravel** est un package qui génère des APIs Laravel complètes à partir de schémas JSON déclaratifs. Conçu pour être utilisé avec des IA comme Claude, ChatGPT, ou pour le développement rapide de prototypes.

## 🚀 Fonctionnalités

- **Génération automatique** de modèles, migrations, contrôleurs et routes
- **Relations complexes** (belongsTo, hasMany, belongsToMany, polymorphes)
- **Validation automatique** basée sur les règles Laravel
- **Système d'authentification** intégré (Laravel Sanctum)
- **Gestion des fichiers** avec storage automatique
- **Hooks et observers** personnalisés
- **Policies et permissions** par rôle
- **Cache automatique** configurable
- **Support complet des scopes** Eloquent
- **Tables pivot** avec champs additionnels

## 📦 Installation

Installez le package via Composer :

```bash
composer require ai-native/laravel
```

Installez les dépendances et la configuration :

```bash
php artisan ai-native:install --sanctum
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
        "password": "string|required|min:8"
      },
      "routes": ["list", "show", "create", "update"]
    },
    "Post": {
      "fields": {
        "user_id": "foreign:users|required",
        "title": "string|required|max:255",
        "content": "longText|required",
        "published": "boolean|default:false"
      },
      "relations": {
        "author": "belongsTo:User,user_id"
      },
      "routes": ["list", "show", "create", "update", "delete"]
    }
  }
}
```

### 2. Générez votre API

```bash
php artisan ai-native:generate schema.json
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

## 📚 Documentation Complète

### Types de Champs Supportés

| Type | Syntaxe | Description |
|------|---------|-------------|
| string | `"string|required|max:255"` | Chaîne de caractères |
| text | `"text|nullable"` | Texte long |
| integer | `"integer|min:0"` | Nombre entier |
| boolean | `"boolean|default:false"` | Booléen |
| date | `"date|nullable"` | Date |
| datetime | `"datetime|nullable"` | Date et heure |
| json | `"json|nullable"` | Données JSON |
| enum | `"enum:draft,published|default:draft"` | Énumération |
| foreign | `"foreign:users|required"` | Clé étrangère |
| file | `"file:images|nullable|image"` | Fichier unique |
| files | `"files:documents|nullable"` | Fichiers multiples |

### Relations Supportées

```json
{
  "relations": {
    "author": "belongsTo:User",
    "posts": "hasMany:Post",
    "tags": "belongsToMany:Tag,post_tag,post_id,tag_id",
    "comments": "morphMany:Comment,commentable",
    "commentable": "morphTo"
  }
}
```

### Scopes Automatiques

```json
{
  "scopes": {
    "published": "where:status,published",
    "recent": "orderBy:created_at,desc",
    "active": "where:is_active,true"
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

## 🛠 Commandes Disponibles

### Génération

```bash
# Générer tous les composants
php artisan ai-native:generate schema.json

# Générer seulement les modèles
php artisan ai-native:generate schema.json --only=models

# Aperçu sans créer de fichiers
php artisan ai-native:generate schema.json --dry-run

# Forcer l'écrasement des fichiers existants
php artisan ai-native:generate schema.json --force
```

### Validation

```bash
# Valider un schéma
php artisan ai-native:validate schema.json
```

### Installation

```bash
# Installation complète avec Sanctum
php artisan ai-native:install --sanctum
```

## 🔧 Configuration Avancée

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
    ],
    // ...
];
```

## 🤖 Optimisation pour l'IA

Ce package est spécialement conçu pour être utilisé avec des IA :

- **Syntaxe simplifiée** : JSON déclaratif facile à générer
- **Contexte réduit** : Moins de tokens nécessaires
- **Patterns standardisés** : Structure cohérente pour l'IA
- **Validation automatique** : Prévient les erreurs communes
- **Documentation intégrée** : Auto-génération des commentaires

### Exemple d'utilisation avec Claude

```
Utilisateur: "Crée-moi une API pour gérer des tâches avec utilisateurs, projets et commentaires"

Claude: Je vais créer un schéma JSON pour votre API de gestion de tâches...

{
  "meta": {
    "project": "TaskAPI",
    "auth": { "enabled": true, "provider": "sanctum" }
  },
  "models": {
    "User": { ... },
    "Project": { ... },
    "Task": { ... },
    "Comment": { ... }
  }
}

Ensuite exécutez : php artisan ai-native:generate task-api.json
```

## 📋 Exemples Complets

### API E-commerce

```json
{
  "meta": {
    "project": "EcommerceAPI",
    "auth": { "enabled": true, "provider": "sanctum" }
  },
  "models": {
    "Product": {
      "fields": {
        "name": "string|required",
        "price": "decimal:8,2|required",
        "stock": "integer|default:0",
        "images": "files:products|nullable"
      },
      "relations": {
        "category": "belongsTo:Category",
        "orders": "belongsToMany:Order,order_product"
      }
    },
    "Order": {
      "fields": {
        "user_id": "foreign:users|required",
        "status": "enum:pending,paid,shipped|default:pending",
        "total": "decimal:10,2|required"
      },
      "relations": {
        "user": "belongsTo:User",
        "products": "belongsToMany:Product,order_product"
      }
    }
  }
}
```

### API Blog avec Tags

```json
{
  "models": {
    "Post": {
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
        "taggable_type": "string|required"
      }
    }
  }
}
```

## 🧪 Tests

```bash
composer test
```

## 📖 Changelog

Consultez [CHANGELOG.md](CHANGELOG.md) pour les détails des versions.

## 🤝 Contribuer

1. Fork le projet
2. Créez une branche (`git checkout -b feature/amazing-feature`)
3. Commitez vos changements (`git commit -m 'Add amazing feature'`)
4. Push vers la branche (`git push origin feature/amazing-feature`)
5. Ouvrez une Pull Request

## 📄 Licence

Ce package est open-source sous licence [MIT](LICENSE.md).

## 🙏 Crédits

- Créé pour faciliter le développement d'APIs avec l'aide de l'IA
- Basé sur Laravel et ses meilleures pratiques
- Inspiré par la philosophie "convention over configuration"

## 🆘 Support

- [Documentation](https://github.com/ai-native/laravel/wiki)
- [Issues GitHub](https://github.com/ai-native/laravel/issues)
- [Discussions](https://github.com/ai-native/laravel/discussions)

---

**AI-Native Laravel** - Générez des APIs Laravel complètes en quelques secondes avec l'IA ! 🚀