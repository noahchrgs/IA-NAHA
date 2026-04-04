# IA-NAHA — AI & Machine Learning Applied to Health, Activity & Nutrition

> Assistant virtuel de santé personnalisé · Gemini AI · scikit-learn · PHP/MySQL · Chart.js

---

## 🇬🇧 English

### 📌 Project Description

IA-NAHA is an AI-driven virtual health assistant that bridges the gap between daily lifestyle habits and optimal recovery.
Using Machine Learning, the system analyzes the synergy between physical activity, stress and biometrics to **predict sleep needs** and provide **personalized nutritional guidance** via the CIQUAL ANSES database.

The system adapts its recommendations based on:
- **Daily Activity** — steps, exercise duration, intensity
- **Biometrics** — BMI, age, gender
- **Psychological state** — perceived stress level
- **Lifestyle** — smoking status, hydration
- **Predicted recovery** — estimated sleep duration (ML model)

### ❓ Problem Statement

> How can AI leverage daily lifestyle and biometric data to predict recovery needs (sleep) and provide automated nutritional guidance to improve overall well-being?

### 🌍 Datasets

| File | Source | Link |
|------|--------|-------|
| `activite_globale.csv` | Gym Members Exercise Tracking (Kaggle) | [kaggle.com/datasets/valakhorasani/gym-members-exercise-dataset](https://www.kaggle.com/datasets/valakhorasani/gym-members-exercise-dataset) |
| `Sleep_health_and_lifestyle_dataset.csv` | Sleep Health and Lifestyle (Kaggle) | [kaggle.com/datasets/uom190346a/sleep-health-and-lifestyle-dataset](https://www.kaggle.com/datasets/uom190346a/sleep-health-and-lifestyle-dataset) |
| `Sport.csv` | PA Compendium (Adult Compendium of Physical Activities) | [pacompendium.com](https://pacompendium.com/adult-compendium/) |
| `Table_ciqual.csv` | ANSES CIQUAL — French food composition database | [ciqual.anses.fr](https://ciqual.anses.fr/#/cms/telechargement/node/20) |

---

## 🇫🇷 Français

### 📌 Description du projet

IA-NAHA est un assistant virtuel intelligent qui analyse l'impact du mode de vie sur la santé globale.
Grâce au Machine Learning, le système prédit les besoins de récupération (sommeil) et propose un **plan nutritionnel personnalisé** généré par **Gemini AI** à partir de la base officielle **CIQUAL ANSES** (2 976 aliments).

### ❓ Problématique

> Comment l'IA peut-elle utiliser les données de vie quotidienne et les constantes biométriques pour prédire les besoins de récupération (sommeil) du grand public, et recommander des ajustements nutritionnels automatisés via la base CIQUAL pour optimiser le bien-être ?

### 🌍 Sources des données

| Fichier | Source | Lien |
|---------|--------|-------|
| `activite_globale.csv` | Gym Members Exercise Tracking (Kaggle) | [kaggle.com/datasets/valakhorasani/gym-members-exercise-dataset](https://www.kaggle.com/datasets/valakhorasani/gym-members-exercise-dataset) |
| `Sleep_health_and_lifestyle_dataset.csv` | Sleep Health and Lifestyle (Kaggle) | [kaggle.com/datasets/uom190346a/sleep-health-and-lifestyle-dataset](https://www.kaggle.com/datasets/uom190346a/sleep-health-and-lifestyle-dataset) |
| `Sport.csv` | PA Compendium (Compendium des Activités Physiques) | [pacompendium.com](https://pacompendium.com/adult-compendium/) |
| `Table_ciqual.csv` | ANSES CIQUAL — base de composition nutritionnelle | [ciqual.anses.fr](https://ciqual.anses.fr/#/cms/telechargement/node/20) |

---

## 🏗️ Architecture du projet

```
IA-NAHA/
│
├── Application/                        # Application web (PHP + HTML/CSS/JS)
│   ├── api/
│   │   ├── config.php                  # Config BDD, helpers JSON, auth Bearer
│   │   ├── login.php                   # Authentification + session token
│   │   ├── register.php                # Inscription utilisateur
│   │   ├── save_plan.php               # Sauvegarde plan nutritionnel
│   │   ├── save_profile.php            # Mise à jour profil utilisateur
│   │   ├── get_plans.php               # Récupération des plans
│   │   ├── get_user.php                # Données utilisateur
│   │   ├── get_stats.php               # Statistiques pour DataViz live
│   │   ├── delete_plan.php             # Suppression plan
│   │   ├── gemini.php                  # Proxy Gemini AI (clé serveur)
│   │   ├── ciqual.php                  # Accès base CIQUAL depuis BDD
│   │   ├── predict_sleep.php           # Appel ML server → prédiction sommeil
│   │   └── ml_server.py                # Serveur Flask (port 5050) — modèle ML
│   │
│   ├── assets/css/                     # Feuilles de style
│   ├── index.html                      # Page d'accueil
│   ├── login.html                      # Connexion / Inscription
│   ├── onboarding.html                 # Formulaire profil utilisateur
│   ├── generate.html                   # Génération plan + sauvegarde
│   ├── dashboard.html                  # Dashboard personnel
│   └── dataviz.html                    # Analyses & Visualisations
│
├── data/
│   ├── Raw/                            # Données brutes originales
│   │   ├── activite_globale.csv        # Gym Members Exercise Tracking (n=3 000)
│   │   ├── Sleep_health_and_lifestyle_dataset.csv  # Sleep Health (Kaggle)
│   │   ├── sommeil_logs.csv            # Logs sommeil filtrés sportifs
│   │   ├── Sport.csv                   # PA Compendium sports
│   │   └── Table_ciqual.csv            # ANSES CIQUAL (2 976 aliments)
│   │
│   └── Cleaned/                        # Données nettoyées et filtrées
│       ├── activite_uniquement_sportifs.csv   # 2 011 sportifs (après filtrage)
│       ├── sommeil_uniquement_sportifs.csv    # 1 196 profils sommeil sportifs
│       ├── ciqual_nutrition.csv               # CIQUAL prétraitée
│       └── compendium_sports.csv              # Compendium nettoyé
│
├── database/
│   ├── ia-naha.sql                     # Dump complet MySQL (schéma + données)
│   └── MCD.png                         # Modèle Conceptuel de Données
│
├── modeles/                            # Modèles ML exportés (scikit-learn)
│   ├── modele_machine_learning.joblib  # LinearRegression entraîné
│   └── scaler_machine_learning.joblib  # StandardScaler ajusté
│
├── Notebook/                           # Jupyter Notebooks
│   ├── Notebook_DataViz.ipynb          # Analyse exploratoire & visualisations
│   └── IA_NAHA_ML_Notebook_FINAL.ipynb # Entraînement & évaluation modèle ML
│
├── Orange_results/                     # Résultats Orange Data Mining
│   ├── Orange_Modèle.ows               # Workflow Orange
│   ├── Importance_Variable.png
│   ├── Regression.png
│   ├── Resultat_Modèle.png
│   └── Orange_Global.png
│
├── .gitignore
└── README.md
```

---

## ⚙️ Stack technique

| Composant | Technologie |
|-----------|-------------|
| Frontend | HTML5, CSS3, JavaScript (Vanilla) |
| Backend | PHP 8+, MySQL (MAMP) |
| IA Génération | Google Gemini 2.5 Flash (API) |
| Modèle ML | Python 3, scikit-learn (LinearRegression + StandardScaler) |
| Serveur ML | Flask (port 5050) |
| DataViz | Chart.js 4 |
| Base alimentaire | CIQUAL ANSES (2 976 aliments en BDD) |
| Notebooks | Jupyter, pandas, seaborn, matplotlib |

## 📊 Modèle ML — Résultats

| Métrique | Valeur |
|----------|--------|
| R² Score | 0,87 |
| MAE | 0,28h |
| RMSE | 0,35h |
| Variables | 10 features (stress, activité, IMC, tabac…) |
| Dataset | 2 011 sportifs (Gym Members, filtré) |
| Split | 80% train / 20% test |

> **Facteur dominant** : `stress_level` (r = −0,41) — seule variable avec une corrélation significative sur la durée de sommeil.
