// nuclen-front-global.ts

/*************************************************
 * 1) Global Declarations
 *************************************************/
declare global {
    // The data structure used by your plugin's PHP:
    const postQuizData: Array<{
      question: string;      // question text
      answers: string[];     // answers, with answers[0] = correct
      explanation: string;   // explanation
    }>;
    
    const NuclenOptinPosition: string;   // 'with_results' | 'before_results'
    const NuclenOptinMandatory: boolean;
    
    // Custom quiz HTML after the results
    const NuclenCustomQuizHtmlAfter: string;
  
    // Google Analytics gtag()
    function gtag(...args: any[]): void;
  
    interface Window {
      NuclenOptinEnabled: boolean;
      NuclenOptinWebhook: string;
      NuclenOptinSuccessMessage: string;
  
      // Allows lazy loading of a component
      NuclenLazyLoadComponent?: (containerId: string, initFunctionName?: string | null) => void;
  
      // The main quiz init function
      nuclearEngagementInitQuiz?: () => void;
  
      // For final results question-by-question review
      nuclearEngagementShowQuizQuestionDetails?: (index: number) => void;
  
      // For retaking quiz
      nuclearEngagementRetakeQuiz?: () => void;
    }
  }
  
  // Force TS to treat this file as a module
  export {};
  