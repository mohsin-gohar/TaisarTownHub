# Firebase Setup & Production Architecture — TaisarTownHub

## Required Services
1. **Firebase Project**: Create at [console.firebase.google.com](https://console.firebase.google.com).
2. **Authentication**: Enable Email/Password provider.
3. **Cloud Firestore**: Provision database in production mode.
4. **Cloud Storage**: Provision storage bucket for media library images, floorplans, and documents.
5. **Environment Configuration**:
   Create `.env.local` or configure Vercel Environment Variables:
   ```env
   VITE_FIREBASE_API_KEY=your_api_key_here
   VITE_FIREBASE_AUTH_DOMAIN=your_project.firebaseapp.com
   VITE_FIREBASE_PROJECT_ID=your_project_id
   VITE_FIREBASE_STORAGE_BUCKET=your_project.appspot.com
   VITE_FIREBASE_MESSAGING_SENDER_ID=your_messaging_sender_id
   VITE_FIREBASE_APP_ID=your_app_id
   VITE_DEMO_MODE=false
   ```

## Media Storage Architecture (Base64 vs Cloud Storage)
- **Current Demo Mode**: Uses `FileReader` + Base64 data URLs in local state for instantaneous zero-config offline demonstration.
- **Production Migration**:
  When Firebase is enabled, media files upload directly to `gs://your-bucket/properties/{propertyId}/{filename}` via Firebase Storage SDK:
  ```ts
  import { getStorage, ref, uploadBytes, getDownloadURL } from 'firebase/storage';
  const storage = getStorage();
  const fileRef = ref(storage, `media/${folder}/${Date.now()}_${file.name}`);
  await uploadBytes(fileRef, file);
  const downloadUrl = await getDownloadURL(fileRef);
  ```

## Security Rules (firestore.rules)
```javascript
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    // Helper functions
    function isAuthenticated() {
      return request.auth != null;
    }
    function isAdminOrSuper() {
      return isAuthenticated() && (request.auth.token.role == 'Super Admin' || request.auth.token.role == 'Admin' || request.auth.token.role == 'super_admin' || request.auth.token.role == 'admin');
    }
    function isManager() {
      return isAuthenticated() && (isAdminOrSuper() || request.auth.token.role == 'Manager');
    }

    // Public properties (read-only for public, restricted write)
    match /properties/{propId} {
      allow read: if true;
      allow write: if isManager();
    }

    // Transactions (Admin and Manager only)
    match /transactions/{txId} {
      allow read: if isManager();
      allow write: if isAdminOrSuper();
    }

    // Sellers & Buyers (Sensitive CNIC & bank data - Admin only)
    match /sellers/{sellerId} {
      allow read, write: if isAdminOrSuper();
    }
    match /buyers/{buyerId} {
      allow read, write: if isManager();
    }
  }
}
```

## Demo Mode Behavior
When `isPresentationMode` is active or `VITE_DEMO_MODE=true`:
- Displays fictional sample properties and transactions.
- Highlights prominent **DEMO MODE** status banner in Admin portal header.
- CNIC masking strictly enforced for unauthorized demo roles.

## Media Storage
Firebase Storage hooks are documented but not wired; current upload uses client-side FileReader (Base64) with size/MIME validation (5 MB max). Replace with Firebase Storage SDK when configured.
