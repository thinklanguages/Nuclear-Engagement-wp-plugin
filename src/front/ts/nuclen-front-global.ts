// file: src/front/ts/nuclen-front-global.ts

/*************************************************
 * 1) Global Declarations
 *************************************************/
declare global {
  interface Window {
    postQuizData: Array<{
      question: string;
      answers: string[];
      explanation: string;
    }>;

    NuclenOptinPosition: string;
    NuclenOptinMandatory: boolean;
    NuclenOptinPromptText: string;
    NuclenOptinButtonText: string;

    NuclenCustomQuizHtmlAfter: string;

    NuclenOptinAjax: {
      url: string;
      nonce: string;
    };

    NuclenSettings: {
      questions_per_quiz: number;
      answers_per_question: number;
    };

    NuclenOptinEnabled: boolean;
    NuclenOptinWebhook: string;
    NuclenOptinSuccessMessage: string;

    NuclenLazyLoadComponent?: (
      containerId: string,
      initFunctionName?: string | null
    ) => void;

    nuclearEngagementInitQuiz?: () => void;
    nuclearEngagementShowQuizQuestionDetails?: (index: number) => void;
    nuclearEngagementRetakeQuiz?: () => void;
  }

  function gtag(...args: any[]): void;
}

export {};
