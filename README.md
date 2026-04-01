# DSA Forge 🔥 — AI-Powered DSA Tracker

> "Developed an AI-powered DSA Tracker with spaced repetition, performance analytics, and mock interview simulation to optimize coding interview preparation."

## 📁 Project Structure

```
dsa-tracker.html          ← Complete frontend (single file, works standalone)
backend/
  api.php                 ← PHP + MySQL backend
  schema.sql              ← MySQL database schema
  server.js               ← Node.js + Express + MongoDB backend
```

---

## 🚀 Quick Start — Frontend Only (No Backend Needed)

Just open `dsa-tracker.html` in any browser. All data saves to localStorage.

**Features available without backend:**
- ✅ Full problem tracking
- ✅ Spaced repetition scheduler
- ✅ Topic progress dashboard
- ✅ Activity heatmap
- ✅ Mock interview timer
- ✅ Analytics & charts
- ✅ Notes with Markdown
- ✅ AI assistant (requires your Anthropic API key)

---

## 🐘 Option A — PHP + MySQL Backend

### 1. Setup Database
```bash
mysql -u root -p < backend/schema.sql
```

### 2. Configure api.php
Edit the DB constants at the top of `backend/api.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'dsa_forge');
```

### 3. Deploy
Place `backend/api.php` in your web server (Apache/Nginx) root.

### 4. Update Frontend API URL
In `dsa-tracker.html`, update the `API_BASE` constant (add near top of `<script>`):
```js
const API_BASE = 'http://localhost/api'; // your server URL
```

---

## 🟢 Option B — Node.js + MongoDB Backend

### 1. Install Dependencies
```bash
cd backend
npm init -y
npm install express cors mongoose dotenv node-fetch
```

### 2. Create .env File
```
PORT=5000
MONGODB_URI=mongodb://localhost:27017/dsa_forge
ANTHROPIC_API_KEY=sk-ant-your-key-here
```

### 3. Start MongoDB
```bash
mongod --dbpath /data/db
```

### 4. Start Server
```bash
node server.js
```

### 5. Update Frontend
```js
const API_BASE = 'http://localhost:5000/api';
```

---

## 🤖 AI Assistant Setup

1. Get an API key from [console.anthropic.com](https://console.anthropic.com)
2. In the app, go to **AI Assistant** tab
3. Enter your key in the "API Key Required" box
4. Key is saved locally and never shared

**Or** use the Node.js backend — set `ANTHROPIC_API_KEY` in `.env` and the AI proxy will handle it server-side (more secure).

---

## ✨ Feature List

| Feature | Status |
|---------|--------|
| Problem Tracking (Add/Edit/Delete) | ✅ |
| LeetCode/CF/GFG Platform tags | ✅ |
| Difficulty tracking (E/M/H) | ✅ |
| Topic categorization | ✅ |
| Status (Solved/Revision/Pending) | ✅ |
| Spaced Repetition (1→3→7→15→30 days) | ✅ |
| Activity Heatmap | ✅ |
| Topic Progress Dashboard | ✅ |
| Difficulty Pie Chart | ✅ |
| Confidence Score (1-5 stars) | ✅ |
| Company Tags (Amazon/Google/etc) | ✅ |
| Markdown Notes & Approach Storage | ✅ |
| Smart Analytics (weak topics) | ✅ |
| Average Solve Time Tracking | ✅ |
| AI Assistant (hints & analysis) | ✅ |
| Daily Challenge System | ✅ |
| Streak System | ✅ |
| Mock Interview Timer | ✅ |
| Random Problem Generator | ✅ |
| Dark/Light Mode | ✅ |
| Offline (localStorage) | ✅ |

---

## 🔌 API Endpoints

### Problems
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/problems | List (supports ?difficulty=&status=&topic=&search=) |
| POST | /api/problems | Create problem |
| PUT | /api/problems/:id | Update problem |
| DELETE | /api/problems/:id | Delete problem |

### Revision
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/revision/due | Today's revision queue |
| POST | /api/revision/:id | Mark revision complete |

### Other
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/analytics | Full analytics data |
| GET | /api/notes | List notes |
| POST | /api/notes | Create note |
| GET | /api/streak | Streak + activity heatmap |
| POST | /api/ai/chat | AI chat proxy (Node.js only) |

---

## 🏆 Resume Tagline

> "Developed **DSA Forge**, an AI-powered DSA Tracker featuring spaced repetition scheduling, GitHub-style activity heatmaps, weak-topic analytics, mock interview simulation, and Claude AI integration — built with vanilla JS (frontend), Node.js/Express (API), and MongoDB (database)."
