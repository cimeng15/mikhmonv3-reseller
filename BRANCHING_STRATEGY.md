# 🌿 Mikhmon V3 - Branching Strategy

## Struktur Branch

```
master (production)
│
├── staging (pre-production / QA)
│
├── develop (development utama)
│   │
│   ├── feature/ui-improvement
│   ├── feature/api-integration
│   ├── feature/<nama-fitur-baru>
│   └── ...
│
└── hotfix/<nama-hotfix> (perbaikan darurat dari master)
```

## 📋 Deskripsi Setiap Branch

| Branch | Fungsi | Merge ke |
|--------|--------|----------|
| `master` | Production - kode stabil & live | - |
| `staging` | Testing/QA sebelum deploy ke production | `master` |
| `develop` | Integrasi semua fitur baru | `staging` |
| `feature/*` | Pengembangan fitur individual | `develop` |
| `hotfix/*` | Perbaikan bug kritis di production | `master` & `develop` |

## 🔄 Workflow

### 1. Membuat Fitur Baru
```bash
# Buat branch fitur dari develop
git checkout develop
git checkout -b feature/nama-fitur

# Kerjakan fitur...
git add .
git commit -m "feat: deskripsi fitur"

# Selesai? Merge ke develop
git checkout develop
git merge feature/nama-fitur
git branch -d feature/nama-fitur
```

### 2. Siap Testing (QA)
```bash
# Merge develop ke staging
git checkout staging
git merge develop

# Testing di staging environment...
```

### 3. Deploy ke Production
```bash
# Setelah staging lolos QA, merge ke master
git checkout master
git merge staging
git tag -a v3.x.x -m "Release v3.x.x"
```

### 4. Hotfix (Perbaikan Darurat)
```bash
# Buat hotfix dari master
git checkout master
git checkout -b hotfix/nama-bug

# Perbaiki bug...
git add .
git commit -m "fix: deskripsi perbaikan"

# Merge ke master DAN develop
git checkout master
git merge hotfix/nama-bug
git tag -a v3.x.x -m "Hotfix v3.x.x"

git checkout develop
git merge hotfix/nama-bug
git branch -d hotfix/nama-bug
```

## 📝 Konvensi Commit Message

Format: `<type>: <deskripsi>`

| Type | Keterangan |
|------|-----------|
| `feat` | Fitur baru |
| `fix` | Perbaikan bug |
| `docs` | Perubahan dokumentasi |
| `style` | Formatting, tanpa perubahan logic |
| `refactor` | Refactoring kode |
| `test` | Menambah/memperbaiki testing |
| `chore` | Maintenance, dependency update |

### Contoh:
```
feat: tambah fitur multi-router dashboard
fix: perbaiki login session timeout
docs: update README instalasi
refactor: optimasi query hotspot user
```

## 📌 Naming Convention Branch

| Tipe | Format | Contoh |
|------|--------|--------|
| Feature | `feature/<deskripsi>` | `feature/multi-router` |
| Bugfix | `bugfix/<deskripsi>` | `bugfix/login-error` |
| Hotfix | `hotfix/<deskripsi>` | `hotfix/session-crash` |
| Release | `release/v<versi>` | `release/v3.21` |

## ⚠️ Aturan Penting

1. **JANGAN** langsung commit ke `master` atau `staging`
2. Selalu buat `feature/*` branch dari `develop`
3. Selalu test di `staging` sebelum merge ke `master`
4. Setiap release di `master` harus diberi **tag versi**
5. Hotfix harus di-merge ke `master` DAN `develop`
