<?php
declare(strict_types=1);
namespace NuclearEngagement\Front;

if (!defined('ABSPATH')) {
    exit;
}

class QuizView {
    public function container(array $settings): string {
        $html = '<section id="nuclen-quiz-container" class="nuclen-quiz">';
        if (trim($settings['html_before']) !== '') {
            $html .= $this->startMessage($settings['html_before']);
        }
        $html .= $this->title($settings['quiz_title']);
        $html .= $this->progressBar();
        $html .= $this->questionContainer();
        $html .= $this->answersContainer();
        $html .= $this->resultContainer();
        $html .= $this->explanationContainer();
        $html .= $this->nextButton();
        $html .= $this->finalResultContainer();
        $html .= '</section>';
        return $html;
    }

    public function attribution(bool $show): string {
        if (!$show) {
            return '';
        }
        return sprintf(
            '<div class="nuclen-attribution">%s <a rel="nofollow" href="https://www.nuclearengagement.com" target="_blank">%s</a></div>',
            esc_html__('Quiz by', 'nuclear-engagement'),
            esc_html__('Nuclear Engagement', 'nuclear-engagement')
        );
    }

    private function startMessage(string $html_before): string {
        return sprintf(
            '<div id="nuclen-quiz-start-message" class="nuclen-fg">%s</div>',
            shortcode_unautop($html_before)
        );
    }

    private function title(string $title): string {
        return sprintf(
            '<h2 id="nuclen-quiz-title" class="nuclen-fg">%s</h2>',
            esc_html($title)
        );
    }

    private function progressBar(): string {
        return '<div id="nuclen-quiz-progress-bar-container"><div id="nuclen-quiz-progress-bar"></div></div>';
    }

    private function questionContainer(): string {
        return '<div id="nuclen-quiz-question-container" class="nuclen-fg"></div>';
    }

    private function answersContainer(): string {
        return '<div id="nuclen-quiz-answers-container" class="nuclen-quiz-answers-grid"></div>';
    }

    private function resultContainer(): string {
        return '<div id="nuclen-quiz-result-container"></div>';
    }

    private function explanationContainer(): string {
        return '<div id="nuclen-quiz-explanation-container" class="nuclen-fg nuclen-quiz-hidden"></div>';
    }

    private function nextButton(): string {
        return sprintf(
            '<button id="nuclen-quiz-next-button" class="nuclen-quiz-hidden">%s</button>',
            esc_html__('Next', 'nuclear-engagement')
        );
    }

    private function finalResultContainer(): string {
        return '<div id="nuclen-quiz-final-result-container"></div>';
    }
}
