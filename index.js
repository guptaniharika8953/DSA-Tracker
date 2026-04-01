/**
 * DSA Forge - Node.js + Express Backend
 * 
 * Setup:
 *   npm install express cors mongoose dotenv
 *   node server.js
 * 
 * .env file:
 *   PORT=5000
 *   MONGODB_URI=mongodb://localhost:27017/dsa_forge
 *   ANTHROPIC_API_KEY=sk-ant-...
 */

require('dotenv').config();
const express  = require('express');
const cors     = require('cors');
const mongoose = require('mongoose');

const app  = express();
const PORT = process.env.PORT || 5000;

app.use(cors());
app.use(express.json());

// ══════════════════════════════════════════════
// MONGOOSE SCHEMAS
// ══════════════════════════════════════════════

const ProblemSchema = new mongoose.Schema({
    name:          { type: String, required: true },
    platform:      { type: String, default: 'LeetCode' },
    difficulty:    { type: String, enum: ['Easy','Medium','Hard'], required: true },
    topic:         { type: String, required: true },
    status:        { type: String, enum: ['Solved','Revision','Pending'], default: 'Pending' },
    timeTaken:     { type: Number, default: 0 },
    confidence:    { type: Number, min: 0, max: 5, default: 0 },
    url:           String,
    companyTags:   [String],
    notes:         String,
    revisionLevel: { type: Number, default: 0 },
    nextRevision:  Date,
    lastReviewed:  Date,
    revisions:     [{ type: Date }],
}, { timestamps: true });

const NoteSchema = new mongoose.Schema({
    title:    { type: String, required: true },
    content:  { type: String, required: true },
    category: { type: String, default: 'General' },
}, { timestamps: true });

const ActivitySchema = new mongoose.Schema({
    date:  { type: String, required: true }, // YYYY-MM-DD
    count: { type: Number, default: 1 }
});

const Problem  = mongoose.model('Problem', ProblemSchema);
const Note     = mongoose.model('Note', NoteSchema);
const Activity = mongoose.model('Activity', ActivitySchema);

// ══════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════

const REVISION_DAYS = [1, 3, 7, 15, 30];

function getNextRevisionDate(level) {
    if (level >= REVISION_DAYS.length) return null;
    const d = new Date();
    d.setDate(d.getDate() + REVISION_DAYS[level]);
    return d;
}

function todayStr() { return new Date().toISOString().split('T')[0]; }

async function logActivity() {
    const today = todayStr();
    await Activity.findOneAndUpdate(
        { date: today },
        { $inc: { count: 1 } },
        { upsert: true, new: true }
    );
}

// ══════════════════════════════════════════════
// PROBLEM ROUTES
// ══════════════════════════════════════════════

// GET /api/problems - List with filters
app.get('/api/problems', async (req, res) => {
    try {
        const query = {};
        if (req.query.difficulty) query.difficulty = req.query.difficulty;
        if (req.query.status)     query.status     = req.query.status;
        if (req.query.topic)      query.topic      = req.query.topic;
        if (req.query.search) {
            const re = new RegExp(req.query.search, 'i');
            query.$or = [{ name: re }, { topic: re }, { companyTags: re }];
        }
        const problems = await Problem.find(query).sort({ createdAt: -1 });
        res.json(problems);
    } catch (e) { res.status(500).json({ error: e.message }); }
});

// POST /api/problems - Create
app.post('/api/problems', async (req, res) => {
    try {
        const { name, platform, difficulty, topic, status, timeTaken, confidence, url, company, notes } = req.body;
        if (!name) return res.status(422).json({ error: 'name is required' });

        const isSolved    = status === 'Solved';
        const nextRevision = isSolved ? getNextRevisionDate(0) : null;

        const problem = new Problem({
            name, platform, difficulty, topic, status,
            timeTaken: timeTaken || 0,
            confidence: confidence || 0,
            url, notes,
            companyTags: company ? company.split(',').map(c => c.trim()) : [],
            revisionLevel: isSolved ? 1 : 0,
            nextRevision,
        });

        await problem.save();
        await logActivity();
        res.status(201).json(problem);
    } catch (e) { res.status(500).json({ error: e.message }); }
});

// PUT /api/problems/:id - Update
app.put('/api/problems/:id', async (req, res) => {
    try {
        const problem = await Problem.findByIdAndUpdate(req.params.id, req.body, { new: true });
        if (!problem) return res.status(404).json({ error: 'Not found' });
        res.json(problem);
    } catch (e) { res.status(500).json({ error: e.message }); }
});

// DELETE /api/problems/:id
app.delete('/api/problems/:id', async (req, res) => {
    try {
        await Problem.findByIdAndDelete(req.params.id);
        res.json({ message: 'Deleted' });
    } catch (e) { res.status(500).json({ error: e.message }); }
});

// ══════════════════════════════════════════════
// REVISION ROUTES
// ══════════════════════════════════════════════

// GET /api/revision/due - Today's revision queue
app.get('/api/revision/due', async (req, res) => {
    try {
        const today = new Date();
        today.setHours(23, 59, 59, 999);
        const problems = await Problem.find({
            nextRevision: { $lte: today },
            status: { $ne: 'Pending' }
        }).sort({ nextRevision: 1 });
        res.json(problems);
    } catch (e) { res.status(500).json({ error: e.message }); }
});

// POST /api/revision/:id - Complete revision
app.post('/api/revision/:id', async (req, res) => {
    try {
        const problem = await Problem.findById(req.params.id);
        if (!problem) return res.status(404).json({ error: 'Not found' });

        const newLevel   = Math.min(problem.revisionLevel + 1, REVISION_DAYS.length);
        const nextRevision = getNextRevisionDate(newLevel - 1);
        const mastered   = nextRevision === null;

        problem.revisionLevel = newLevel;
        problem.nextRevision  = nextRevision;
        problem.lastReviewed  = new Date();
        problem.revisions.push(new Date());
        if (mastered) problem.status = 'Solved';

        await problem.save();
        await logActivity();

        res.json({ nextRevision, level: newLevel, mastered, problem });
    } catch (e) { res.status(500).json({ error: e.message }); }
});

// ══════════════════════════════════════════════
// ANALYTICS
// ══════════════════════════════════════════════

app.get('/api/analytics', async (req, res) => {
    try {
        const all    = await Problem.find();
        const solved = all.filter(p => p.status === 'Solved');
        const total  = all.length;

        const accuracy  = total > 0 ? Math.round((solved.length / total) * 100) : 0;
        const avgTime   = solved.length > 0
            ? (solved.reduce((s, p) => s + (p.timeTaken || 0), 0) / solved.length).toFixed(1)
            : 0;
        const avgConf   = solved.length > 0
            ? (solved.reduce((s, p) => s + (p.confidence || 0), 0) / solved.length).toFixed(1)
            : 0;

        // By topic
        const topicMap = {};
        all.forEach(p => {
            if (!topicMap[p.topic]) topicMap[p.topic] = { total: 0, solved: 0 };
            topicMap[p.topic].total++;
            if (p.status === 'Solved') topicMap[p.topic].solved++;
        });
        const byTopic = Object.entries(topicMap).map(([topic, data]) => ({
            topic, ...data, pct: Math.round((data.solved / data.total) * 100)
        }));

        // By difficulty
        const byDiff = ['Easy','Medium','Hard'].map(d => ({
            difficulty: d,
            count: all.filter(p => p.difficulty === d).length
        }));

        // Weak topics (< 50% solved, >= 2 problems)
        const weakTopics = byTopic.filter(t => t.total >= 2 && t.pct < 50)
            .sort((a, b) => a.pct - b.pct)
            .slice(0, 5);

        // Company counts
        const companyCounts = {};
        all.forEach(p => (p.companyTags || []).forEach(c => {
            companyCounts[c] = (companyCounts[c] || 0) + 1;
        }));
        const topCompanies = Object.entries(companyCounts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 10)
            .reduce((obj, [k, v]) => ({ ...obj, [k]: v }), {});

        res.json({
            total, solved: solved.length, accuracy,
            avgTime, avgConf, byTopic, byDiff,
            weakTopics, topCompanies
        });
    } catch (e) { res.status(500).json({ error: e.message }); }
});

// ══════════════════════════════════════════════
// NOTES
// ══════════════════════════════════════════════

app.get('/api/notes', async (req, res) => {
    try {
        res.json(await Note.find().sort({ createdAt: -1 }));
    } catch (e) { res.status(500).json({ error: e.message }); }
});

app.post('/api/notes', async (req, res) => {
    try {
        const { title, content, category } = req.body;
        if (!title || !content) return res.status(422).json({ error: 'title and content required' });
        const note = new Note({ title, content, category });
        await note.save();
        res.status(201).json(note);
    } catch (e) { res.status(500).json({ error: e.message }); }
});

app.delete('/api/notes/:id', async (req, res) => {
    try {
        await Note.findByIdAndDelete(req.params.id);
        res.json({ message: 'Deleted' });
    } catch (e) { res.status(500).json({ error: e.message }); }
});

// ══════════════════════════════════════════════
// STREAK & ACTIVITY
// ══════════════════════════════════════════════

app.get('/api/streak', async (req, res) => {
    try {
        const activity = await Activity.find().sort({ date: -1 }).limit(180);
        const actMap   = activity.reduce((m, a) => ({ ...m, [a.date]: a.count }), {});

        // Calculate streak
        let streak = 0;
        let d = new Date();
        while (true) {
            const key = d.toISOString().split('T')[0];
            if (!actMap[key]) break;
            streak++;
            d.setDate(d.getDate() - 1);
        }

        res.json({ streak, activity: actMap });
    } catch (e) { res.status(500).json({ error: e.message }); }
});

// ══════════════════════════════════════════════
// AI PROXY (keeps API key server-side)
// ══════════════════════════════════════════════

app.post('/api/ai/chat', async (req, res) => {
    try {
        const { message } = req.body;
        const fetch = (await import('node-fetch')).default;

        const resp = await fetch('https://api.anthropic.com/v1/messages', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'x-api-key': process.env.ANTHROPIC_API_KEY,
                'anthropic-version': '2023-06-01',
            },
            body: JSON.stringify({
                model: 'claude-opus-4-5',
                max_tokens: 1000,
                system: 'You are an expert DSA tutor for coding interview prep. Give hints, not full solutions. Be concise and encouraging.',
                messages: [{ role: 'user', content: message }]
            })
        });

        const data = await resp.json();
        res.json({ reply: data.content?.[0]?.text || 'Error' });
    } catch (e) { res.status(500).json({ error: e.message }); }
});

// ══════════════════════════════════════════════
// START
// ══════════════════════════════════════════════

mongoose.connect(process.env.MONGODB_URI || 'mongodb://localhost:27017/dsa_forge')
    .then(() => {
        console.log('✅ MongoDB connected');
        app.listen(PORT, () => console.log(`🚀 DSA Forge API running on http://localhost:${PORT}`));
    })
    .catch(err => console.error('❌ MongoDB connection failed:', err));