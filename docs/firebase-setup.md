# Firebase Setup Instructions - Turns Project

## 🔥 Firebase Authentication & Analytics - Configured for Existing Apps

The Firebase implementation is **complete and configured** for your existing Firebase project `turns-ccc9e` with the correct app identifiers:

- ✅ **Android App**: `za.co.ingenio.turns` (not `com.ingenio.turns.turnsFlutter`)
- ✅ **Web App**: `turns_web` (not `turns_flutter`)
- ✅ **Project**: `turns-ccc9e`

## 📋 What's Already Implemented

✅ **Firebase Admin SDK** - Full server-side integration  
✅ **Authentication Exchange** - `POST /api/auth/firebase/exchange`  
✅ **Analytics Tracking** - User behavior and login events  
✅ **Database Schema** - Firebase fields added to users table  
✅ **Feature Tests** - Comprehensive test coverage  
✅ **Graceful Fallback** - Works without Firebase for development  

## 🔧 Setup Instructions

### 1. Get Firebase Service Account Credentials

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Select your project (or create one)
3. Go to **Project Settings** (gear icon) → **Service Accounts**
4. Click **"Generate new private key"**
5. Download the JSON file

### 2. Update .env File

Your project configuration is already detected! Just replace the service account credentials:

```env
# Project Configuration (Already Set)
FIREBASE_PROJECT_ID=turns-ccc9e
FIREBASE_API_KEY=AIzaSyCOBASqcrJ2CJmhOxxIULkWIJc1hP1yiB4
FIREBASE_AUTH_DOMAIN=turns-ccc9e.firebaseapp.com
FIREBASE_STORAGE_BUCKET=turns-ccc9e.firebasestorage.app
FIREBASE_MESSAGING_SENDER_ID=204340310004
FIREBASE_WEB_APP_ID=1:204340310004:web:a5cbf9cbf1805ef7a913a1
FIREBASE_MEASUREMENT_ID=G-71XJ4P3X63

# App Identifiers (Corrected)
FIREBASE_ANDROID_PACKAGE=za.co.ingenio.turns
FIREBASE_WEB_APP_NAME=turns_web

# Service Account Credentials (Replace with your values)
FIREBASE_PRIVATE_KEY_ID=your-private-key-id-from-service-account-json
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nYOUR_ACTUAL_PRIVATE_KEY_CONTENT\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=firebase-adminsdk-xxxxx@turns-ccc9e.iam.gserviceaccount.com
FIREBASE_CLIENT_ID=your-client-id-from-service-account-json
FIREBASE_CLIENT_CERT_URL=https://www.googleapis.com/robot/v1/metadata/x509/firebase-adminsdk-xxxxx%40turns-ccc9e.iam.gserviceaccount.com
```

### 3. Mapping Service Account JSON to .env

From your downloaded `turns-ccc9e-service-account-key.json`:

```json
{
  "type": "service_account",
  "project_id": "turns-ccc9e",              ✅ Already configured
  "private_key_id": "key123",               → FIREBASE_PRIVATE_KEY_ID
  "private_key": "-----BEGIN PRIVATE...",   → FIREBASE_PRIVATE_KEY
  "client_email": "firebase-adminsdk-xxx@turns-ccc9e.iam.gserviceaccount.com", → FIREBASE_CLIENT_EMAIL
  "client_id": "123456789",                 → FIREBASE_CLIENT_ID
  "client_x509_cert_url": "https://..."     → FIREBASE_CLIENT_CERT_URL
}
```

## ✅ Corrected App Configuration

- **Android Package**: `za.co.ingenio.turns` ✅ (was incorrectly `com.ingenio.turns.turnsFlutter`)
- **Web App Name**: `turns_web` ✅ (was incorrectly `turns_flutter`)  
- **Project ID**: `turns-ccc9e` ✅
- **Web App ID**: `1:204340310004:web:a5cbf9cbf1805ef7a913a1` ✅

## 🧪 Testing the Implementation

### 1. Check Configuration
```bash
php artisan tinker --execute="
\$firebase = app(\App\Application\Services\FirebaseService::class);
echo 'Firebase configured: ' . (\$firebase->isConfigured() ? 'Yes' : 'No') . PHP_EOL;
"
```

### 2. Test the Endpoint

**Before Configuration** (current state):
```bash
curl -X POST "http://localhost:8000/api/auth/firebase/exchange" \
  -H "Content-Type: application/json" \
  -d '{"idToken": "test"}'

# Returns: 501 "Firebase authentication is not configured"
```

**After Configuration**:
```bash
curl -X POST "http://localhost:8000/api/auth/firebase/exchange" \
  -H "Content-Type: application/json" \
  -d '{"idToken": "REAL_FIREBASE_ID_TOKEN"}'

# Returns: 200 with user data and API token
```

## 🎯 How It Works

### Authentication Flow
1. **Client** (Flutter/Web) authenticates with Firebase → gets ID token
2. **Client** sends ID token to `POST /api/auth/firebase/exchange`
3. **Backend** verifies token with Firebase Admin SDK
4. **Backend** finds/creates user account
5. **Backend** returns Sanctum API token for backend access
6. **Client** uses API token for all subsequent requests

### Analytics Tracking
- Login events automatically tracked
- User behavior data stored in `firebase_analytics_data` field
- Configurable debug mode and event filtering
- Privacy-compliant data collection

## 🔄 Current State

- ✅ **Development**: Email/password auth fully working
- ⚠️ **Production**: Firebase ready but needs credentials
- ✅ **API Contract**: Compliant with documented endpoints
- ✅ **Database**: All Firebase fields added
- ✅ **Testing**: Feature tests ready

## 🚀 Next Steps

1. Add your Firebase credentials to `.env`
2. Restart Laravel server
3. Test with real Firebase ID tokens
4. Configure your Flutter/Web clients to use the exchange endpoint
5. Monitor with Telescope at `/telescope` for debugging

The implementation is production-ready once you add the credentials!
