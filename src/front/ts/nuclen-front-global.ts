// file: src/front/ts/nuclen-front-global.ts

/*************************************************
 * 1) Global Declarations
 *************************************************/
declare global {
  const postQuizData: Array<{
    question: string;
    answers: string[];
    explanation: string;
  }>;

  const NuclenOptinPosition: string;
  const NuclenOptinMandatory: boolean;
  const NuclenOptinPromptText: string;
  const NuclenOptinButtonText: string;

  const NuclenCustomQuizHtmlAfter: string;

  const NuclenOptinAjax: {
    url: string;
    nonce: string;
  };

  function gtag(...args: any[]): void;

  interface Window {
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
}

export {};
