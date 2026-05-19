<?php
// chatbot/retriever.php
// ============================================================
// Lexical retrieval over a symptom-disease knowledge base.
// Pure-PHP implementation: tokenisation -> red-flag check ->
// weighted keyword matching -> ranked diseases.
//
// NOTE: This is retrieval only. It does NOT call an LLM.
// To upgrade to true RAG, pass the retrieved diseases as
// context to an LLM in api.php. See the comment in api.php.
// ============================================================

class SymptomRetriever
{
    private $kb;

    // Words to drop during tokenisation. Kept short on purpose:
    // medical phrasing often uses words a generic stopword list
    // would discard (e.g. "no", "not"). Add cautiously.
    private static $stopwords = [
        'i','me','my','have','had','am','is','are','a','an','the',
        'of','to','for','and','or','with','some','also','really',
        'just','very','been','got','feel','feeling','having','since',
        'about','around','it','its','this','that','these','those',
        'on','in','at','from','was','were','do','does','did'
    ];

    public function __construct($kbPath)
    {
        if (!file_exists($kbPath)) {
            throw new Exception("Knowledge base not found at: $kbPath");
        }
        $raw = file_get_contents($kbPath);
        $this->kb = json_decode($raw, true);
        if ($this->kb === null) {
            throw new Exception("Invalid JSON in knowledge base: " . json_last_error_msg());
        }
    }

    public function disclaimer() {
        return $this->kb['disclaimer'] ?? '';
    }

    // ── Normalise user text to a searchable lowercase string ──
    private function normalise($text) {
        $text = strtolower($text);
        // Common contractions / variants that the KB is keyed on
        $text = str_replace(["’", "‘", "`"], "'", $text);
        // Collapse whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return $text;
    }

    // Tokenise into single words (stopwords removed). Used for
    // unigram fallback matching.
    private function tokens($normText) {
        // Strip punctuation
        $clean = preg_replace('/[^a-z0-9 \']+/', ' ', $normText);
        $parts = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($parts as $p) {
            if (in_array($p, self::$stopwords, true)) continue;
            if (strlen($p) < 2) continue;
            $out[] = $p;
        }
        return $out;
    }

    // ── Phrase containment check ──
    // A phrase like "shortness of breath" matches if it occurs
    // in the normalised text as a substring with word boundaries.
    private function phraseMatches($phrase, $normText) {
        $phrase = strtolower(trim($phrase));
        if ($phrase === '') return false;

        // 1. Exact substring with word boundaries (preferred)
        $pattern = '/(^|[^a-z0-9])' . preg_quote($phrase, '/') . '([^a-z0-9]|$)/';
        if (preg_match($pattern, $normText)) return true;

        // 2. Fuzzy fallback for multi-word phrases:
        //    Tokenise both phrase and text, drop common filler words,
        //    crude-stem each, and require ALL content stems of the
        //    phrase to appear in the text. Catches things like
        //    "burning when urinating" matching "burning when I urinate".
        return $this->fuzzyPhraseMatch($phrase, $normText);
    }

    // Tiny stopword list specific to phrase fuzzy match — broader than
    // the user-input one, because we're stripping function words from
    // the *KB phrase* to find its content core.
    private static $phraseFiller = [
        'when','in','of','a','an','the','and','or','to','for','with',
        'at','on','my','i','is','are','am','it','its','this','that',
        'be','been','have','has','had'
    ];

    private function fuzzyPhraseMatch($phrase, $normText) {
        $words = preg_split('/\s+/', preg_replace('/[^a-z0-9 ]+/', ' ', $phrase));
        $content = [];
        foreach ($words as $w) {
            if ($w === '') continue;
            if (in_array($w, self::$phraseFiller, true)) continue;
            if (strlen($w) < 3) continue;
            $content[] = $this->stem($w);
        }
        // Need at least 2 content tokens to fuzzy-match safely.
        // Single-word phrases must match exactly to avoid false positives
        // (e.g. matching "fever" in "I had no fever").
        if (count($content) < 2) return false;

        // Stem the user text into a set
        $textWords = preg_split('/\s+/', preg_replace('/[^a-z0-9 ]+/', ' ', $normText));
        $textStems = [];
        foreach ($textWords as $w) {
            if ($w === '' || strlen($w) < 2) continue;
            $textStems[$this->stem($w)] = true;
        }

        foreach ($content as $stem) {
            if (!isset($textStems[$stem])) return false;
        }
        return true;
    }

    // Crude rule-based stemmer. Conservative on purpose — over-
    // stemming creates spurious matches. Only strips common English
    // suffixes when the resulting stem is still ≥ 3 characters.
    private function stem($w) {
        $len = strlen($w);
        if ($len >= 6 && substr($w, -3) === 'ing') return substr($w, 0, $len - 3);
        if ($len >= 6 && substr($w, -3) === 'ies') return substr($w, 0, $len - 3) . 'y';
        if ($len >= 5 && substr($w, -2) === 'ed')  return substr($w, 0, $len - 2);
        if ($len >= 5 && substr($w, -2) === 'es')  return substr($w, 0, $len - 2);
        if ($len >= 5 && substr($w, -1) === 'y')   return substr($w, 0, $len - 1);
        if ($len >= 5 && substr($w, -1) === 'e')   return substr($w, 0, $len - 1);
        if ($len >= 4 && substr($w, -1) === 's' && substr($w, -2) !== 'ss')
            return substr($w, 0, $len - 1);
        return $w;
    }

    // ── Red-flag check ──
    // Runs BEFORE disease scoring. If a red-flag pattern fires,
    // we return that and skip the ranked-disease output. Patterns
    // can require ALL groups to fire (each group = OR over phrases),
    // and/or ANY phrases.
    public function checkRedFlags($normText) {
        $hits = [];
        foreach ($this->kb['red_flags'] as $flag) {
            $allOk = true;

            // match_all: every group must have at least one phrase hit
            if (!empty($flag['match_all'])) {
                foreach ($flag['match_all'] as $group) {
                    $groupHit = false;
                    foreach ($group as $phrase) {
                        if ($this->phraseMatches($phrase, $normText)) {
                            $groupHit = true;
                            break;
                        }
                    }
                    if (!$groupHit) { $allOk = false; break; }
                }
            }
            if (!$allOk) continue;

            // match_any: at least one phrase across any group must hit
            if (!empty($flag['match_any'])) {
                $anyHit = false;
                foreach ($flag['match_any'] as $group) {
                    foreach ($group as $phrase) {
                        if ($this->phraseMatches($phrase, $normText)) {
                            $anyHit = true;
                            break 2;
                        }
                    }
                }
                if (!$anyHit) continue;
            }

            // If we got here, this red flag triggered
            $hits[] = $flag;
        }
        return $hits;
    }

    // ── Score a single disease against the normalised user text ──
    private function scoreDisease($disease, $normText, $userTokens) {
        $totalWeight  = 0;
        $matchedWeight = 0;
        $matchedSymptoms = [];

        foreach ($disease['symptoms'] as $sym) {
            $w = $sym['weight'];
            $totalWeight += $w;

            $hit = false;
            foreach ($sym['keywords'] as $kw) {
                if ($this->phraseMatches($kw, $normText)) {
                    $hit = true;
                    $matchedSymptoms[] = $kw;
                    break;
                }
                // Unigram fallback: if the keyword is a single
                // distinctive word, also accept token-level match.
                // We DON'T do this for short common words.
                if (!str_contains($kw, ' ') && strlen($kw) >= 5
                    && in_array($kw, $userTokens, true)) {
                    $hit = true;
                    $matchedSymptoms[] = $kw;
                    break;
                }
            }
            if ($hit) $matchedWeight += $w;
        }

        if ($totalWeight == 0) return null;

        // Score = recall over weighted symptoms (0..1)
        // Penalise diseases where almost no symptoms matched.
        $recall = $matchedWeight / $totalWeight;
        if (count($matchedSymptoms) === 0) return null;

        // Bonus: more matched symptoms = more confident (vs. one
        // strong keyword carrying a disease). Capped contribution.
        $countBonus = min(0.15, count($matchedSymptoms) * 0.04);

        $score = $recall + $countBonus;

        return [
            'disease'         => $disease,
            'score'           => $score,
            'matched_symptoms'=> array_values(array_unique($matchedSymptoms)),
            'matched_count'   => count($matchedSymptoms),
        ];
    }

    // ── Top-K disease retrieval ──
    public function retrieve($userText, $k = 3) {
        $norm = $this->normalise($userText);
        $tokens = $this->tokens($norm);

        // 1. Red flags first — these short-circuit the response
        $redFlags = $this->checkRedFlags($norm);
        if (!empty($redFlags)) {
            return [
                'red_flags' => $redFlags,
                'diseases'  => [],
                'tokens'    => $tokens,
            ];
        }

        // 2. Score every disease
        $scored = [];
        foreach ($this->kb['diseases'] as $d) {
            $r = $this->scoreDisease($d, $norm, $tokens);
            if ($r !== null) $scored[] = $r;
        }

        // 3. Sort descending by score
        usort($scored, function($a, $b) {
            if ($a['score'] == $b['score']) return 0;
            return ($a['score'] < $b['score']) ? 1 : -1;
        });

        // 4. Confidence threshold — drop diseases with very weak
        // matches so we don't recommend a cardiologist for "I feel
        // a bit off". Calibrated against the test set; lower bound
        // chosen so a single weight-2 hit on a ~14-weight disease
        // (~14% recall) just clears.
        $filtered = array_filter($scored, function($r) {
            return $r['score'] >= 0.15 && $r['matched_count'] >= 1;
        });

        return [
            'red_flags' => [],
            'diseases'  => array_slice(array_values($filtered), 0, $k),
            'tokens'    => $tokens,
        ];
    }
}
