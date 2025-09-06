# Turns Laravel Backend

> **Organization**: inGenIO  
> **Repository**: https://github.com/ingenioza/turns  
> **Project**: Laravel backend for Turns group turn-taking application  

A comprehensive backend API for the Turns app built with Laravel 12, implementing Domain-Driven Design (DDD) architecture.

## 🏗️ Architecture

This project follows **Domain-Driven Design (DDD)** principles with a clean, modular structure:

```
app/
├── Domain/                 # Domain Layer - Business Logic
│   ├── User/              # User domain
│   │   ├── User.php       # User entity
│   │   └── UserRepositoryInterface.php
│   ├── Group/             # Group domain
│   │   ├── Group.php      # Group entity
│   │   └── GroupRepositoryInterface.php
│   └── Turn/              # Turn domain
│       ├── Turn.php       # Turn entity
│       └── TurnRepositoryInterface.php
├── Application/           # Application Layer - Use Cases
│   └── Services/          # Application services
│       ├── UserService.php
│       ├── GroupService.php
│       └── TurnService.php
├── Infrastructure/        # Infrastructure Layer - Data Access
│   └── Repositories/      # Repository implementations
│       ├── EloquentUserRepository.php
│       ├── EloquentGroupRepository.php
│       └── EloquentTurnRepository.php
└── Http/                  # Presentation Layer
    └── Controllers/
        └── Api/           # API controllers
```

## 🚀 Features

### Authentication & User Management
- **Sanctum API Authentication** - Token-based auth for mobile apps
- **User Registration & Login** - Secure user account management
- **Password Management** - Change passwords securely
- **User Settings** - Customizable user preferences
- **Activity Tracking** - Track user activity and login history

### Group Management
- **Create Groups** - Users can create and manage groups
- **Invite System** - Unique invite codes for joining groups
- **Member Management** - Add/remove members, assign roles
- **Group Settings** - Configurable group preferences
- **Group Statistics** - Track group activity and turn history

### Turn Management
- **Turn Tracking** - Start, complete, skip, and manage turns
- **Turn History** - Complete history of all turns
- **Auto-Expiration** - Automatically expire stale turns
- **Turn Statistics** - Analytics for users and groups
- **Real-time Updates** - Live turn status updates

### Administrative Features
- **Activity Logging** - Complete audit trail using Spatie ActivityLog
- **Permission System** - Role-based access control with Spatie Permissions
- **Background Jobs** - Queue processing with Laravel Horizon
- **Data Validation** - Comprehensive validation using Spatie Data
- **Query Optimization** - Advanced querying with Spatie Query Builder

## 🛠️ Tech Stack

- **Laravel 12** - Latest Laravel framework
- **PHP 8.3+** - Modern PHP features
- **Sanctum** - API authentication
- **Horizon** - Queue management and monitoring
- **Spatie Packages**:
  - `laravel-activitylog` - Activity logging
  - `laravel-permission` - Role & permission management
  - `laravel-data` - Data transfer objects
  - `laravel-query-builder` - Advanced API filtering

## 🗄️ Database Schema

### Users Table
```sql
- id (primary key)
- name, email, username
- password, email_verified_at
- avatar_url, last_active_at
- settings (JSON), status, is_admin
- timestamps
```

### Groups Table
```sql
- id (primary key)
- name, description, creator_id
- invite_code (unique), status
- settings (JSON), turn_history (JSON)
- last_turn_at, current_user_id
- timestamps
```

### Turns Table
```sql
- id (primary key)
- group_id, user_id
- started_at, ended_at, status
- duration_seconds, notes, metadata (JSON)
- timestamps
```

### Group Members (Pivot)
```sql
- group_id, user_id
- role, joined_at, is_active
- turn_order, settings (JSON)
- timestamps
```

## 📡 API Endpoints

### Authentication
```
POST   /api/auth/register           # Register new user
POST   /api/auth/login              # Login user
GET    /api/auth/me                 # Get current user
POST   /api/auth/logout             # Logout current session
POST   /api/auth/logout-all         # Logout all sessions
POST   /api/auth/change-password    # Change password
POST   /api/auth/settings           # Update user settings
```

### Users
```
GET    /api/users                   # List users
GET    /api/users/{user}            # Get user details
PUT    /api/users/{user}            # Update user
DELETE /api/users/{user}            # Deactivate user
GET    /api/users/search            # Search users
GET    /api/users/recently-active   # Get recently active users
GET    /api/users/{user}/groups     # Get user's groups
POST   /api/users/{user}/settings   # Update user settings
```

### Groups
```
GET    /api/groups                  # List user's groups
POST   /api/groups                  # Create group
GET    /api/groups/{group}          # Get group details
PUT    /api/groups/{group}          # Update group
DELETE /api/groups/{group}          # Archive group
POST   /api/groups/join             # Join group by invite code
GET    /api/groups/search           # Search groups
POST   /api/groups/{group}/leave    # Leave group
GET    /api/groups/{group}/members  # Get group members
DELETE /api/groups/{group}/members/{member}      # Remove member
PATCH  /api/groups/{group}/members/{member}/role # Update member role
```

### Turns
```
GET    /api/turns                           # List user's turns
POST   /api/turns                           # Start new turn
GET    /api/turns/{turn}                    # Get turn details
POST   /api/turns/{turn}/complete           # Complete turn
POST   /api/turns/{turn}/skip               # Skip turn
POST   /api/turns/{turn}/force-end          # Force end turn (admin)
GET    /api/turns/user-stats                # Get user statistics

# Group-specific turn endpoints
GET    /api/groups/{group}/turns/active     # Get active turn
GET    /api/groups/{group}/turns/current    # Get current turn
GET    /api/groups/{group}/turns/history    # Get turn history
GET    /api/groups/{group}/turns/stats      # Get group statistics
```

## 🚀 Getting Started

### Prerequisites
- PHP 8.3+
- Composer
- MySQL/PostgreSQL
- Redis (for queues)

### Installation

1. **Install dependencies**
   ```bash
   composer install
   ```

2. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure database**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=turns_laravel
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

4. **Run migrations**
   ```bash
   php artisan migrate
   ```

5. **Start the server**
   ```bash
   php artisan serve
   ```

### Queue Setup (Optional but Recommended)

1. **Configure Redis**
   ```env
   QUEUE_CONNECTION=redis
   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379
   ```

2. **Start Horizon**
   ```bash
   php artisan horizon
   ```

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

## 🔧 Development Commands

### Turn Management
```bash
# Expire old turns manually
php artisan turns:expire-old

# Queue turn expiration job
php artisan queue:work
```

### Database
```bash
# Fresh migration with seeding
php artisan migrate:fresh --seed

# Check migration status
php artisan migrate:status

# Rollback migrations
php artisan migrate:rollback
```

### Monitoring
```bash
# Monitor Horizon queues
php artisan horizon

# View application logs
php artisan pail
```

## 📊 Monitoring & Logging

### Activity Logging
All user actions are automatically logged using Spatie ActivityLog:
- User registrations and logins
- Group creation and membership changes
- Turn starts, completions, and skips
- Administrative actions

### Queue Monitoring
Use Laravel Horizon to monitor background jobs:
- Queue status and throughput
- Failed job management
- Real-time metrics

### Performance Monitoring
- Database query optimization
- Response time tracking
- Memory usage monitoring

## 🔒 Security Features

- **API Token Authentication** - Sanctum tokens for secure API access
- **Role-Based Access Control** - Admin and member roles
- **Activity Auditing** - Complete audit trail
- **Input Validation** - Comprehensive request validation
- **Rate Limiting** - API rate limiting protection
- **CORS Configuration** - Secure cross-origin requests

## 🚀 Deployment

### Production Setup
1. Set `APP_ENV=production`
2. Configure production database
3. Set up Redis for caching and queues
4. Configure web server (Nginx/Apache)
5. Set up SSL certificates
6. Configure monitoring and logging

### Environment Variables
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=turns_production

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail (for notifications)
MAIL_MAILER=smtp
MAIL_HOST=your-mail-host
```

## 🤝 Integration with Flutter App

This backend provides a complete REST API that the Flutter app can consume:

1. **Authentication Flow**: Register/login endpoints for user management
2. **Real-time Updates**: API endpoints for real-time turn status
3. **Group Management**: Complete group lifecycle management
4. **Turn Tracking**: Comprehensive turn management system
5. **Statistics**: Analytics and reporting capabilities

## 📚 API Documentation

The API follows REST conventions with consistent response formats:

### Success Response
```json
{
  "message": "Success message",
  "data": { ... },
  "user": { ... },
  "group": { ... }
}
```

### Error Response
```json
{
  "message": "Error message",
  "errors": {
    "field": ["Validation error"]
  }
}
```

### Authentication
All protected endpoints require the `Authorization` header:
```
Authorization: Bearer {your-api-token}
```

## 🔄 Development Workflow

1. **Feature Development**: Create feature branches
2. **Testing**: Write comprehensive tests
3. **Code Review**: Review all changes
4. **Integration**: Merge to development branch
5. **Deployment**: Deploy to staging/production

## 📞 Support

For questions or issues:
1. Check the documentation
2. Review existing issues
3. Create a new issue with detailed information
4. Contact the development team

---

**Built with ❤️ using Laravel 12 and Domain-Driven Design**

- **RESTful API**: Complete API for mobile/web app integration
- **Web Interface**: Full-featured web application using Inertia.js + React
- **Authentication**: Multi-provider OAuth (Google, Apple) + email/password
- **Group Management**: Persistent groups with participant management
- **Turn Algorithms**: Multiple algorithms (random, round-robin, weighted)
- **Notifications**: Push notifications and activity logging
- **Activity Tracking**: Comprehensive logging using Spatie Activity Log
- **Permissions**: Role-based permissions using Spatie Permissions

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.4)
- **Frontend**: Inertia.js + React 18 + TypeScript
- **Styling**: Tailwind CSS
- **Database**: MySQL/PostgreSQL (configurable)
- **Testing**: PHPUnit
- **Queue**: Redis (for notifications and background jobs)
- **Storage**: Local/S3 (configurable)

## License

This project is licensed under the MIT License.

---

## 🏢 Organization

**inGenIO** - Building innovative productivity and collaboration tools

- **Organization**: [inGenIO GitHub](https://github.com/ingenioza)
- **Main Repository**: [turns](https://github.com/ingenioza/turns)
- **Flutter App**: [turns-flutter](https://github.com/ingenioza/turns-flutter)
- **Project Documentation**: See main repository for complete project overview
