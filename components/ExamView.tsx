
import React, { useState, useEffect } from 'react';
import { TestSession, Question } from '../types';
import { useLanguage } from '../App';
import { TRANSLATIONS } from '../constants';
import { Clock, CheckCircle, XCircle, AlertTriangle } from 'lucide-react';
import { saveSession } from '../services/storageService';

interface ExamViewProps {
  session: TestSession;
  onExit: () => void;
}

const ExamView: React.FC<ExamViewProps> = ({ session, onExit }) => {
  const { lang } = useLanguage();
  const t = TRANSLATIONS[lang];
  const [currentQIndex, setCurrentQIndex] = useState(0);
  const [answers, setAnswers] = useState<Record<string, string>>(session.answers);
  const [timeLeft, setTimeLeft] = useState<number>(60 * 60); // 60 minutes in seconds
  const [isFinished, setIsFinished] = useState(session.isCompleted);
  const [localSession, setLocalSession] = useState(session);

  useEffect(() => {
    // Restore time based on start time if reloading (simplified logic: just fresh timer for this demo)
    // In production, calc Date.now() - session.startTime
    const elapsed = Math.floor((Date.now() - session.startTime) / 1000);
    const remaining = (60 * 60) - elapsed;
    if (remaining <= 0) {
        finishTest();
    } else {
        setTimeLeft(remaining);
    }
  }, []);

  useEffect(() => {
    if (isFinished) return;
    const timer = setInterval(() => {
      setTimeLeft((prev) => {
        if (prev <= 1) {
          clearInterval(timer);
          finishTest();
          return 0;
        }
        return prev - 1;
      });
    }, 1000);
    return () => clearInterval(timer);
  }, [isFinished]);

  const formatTime = (seconds: number) => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${s < 10 ? '0' : ''}${s}`;
  };

  const handleAnswer = (option: string) => {
    if (isFinished) return;
    const qId = localSession.questions[currentQIndex].id;
    const newAnswers = { ...answers, [qId]: option };
    setAnswers(newAnswers);
    
    // Auto save progress
    const updated = { ...localSession, answers: newAnswers };
    setLocalSession(updated);
    saveSession(updated);
  };

  const finishTest = () => {
    setIsFinished(true);
    // Calculate score
    let score = 0;
    localSession.questions.forEach(q => {
        if (answers[q.id] === q.correctAnswer) score++;
    });
    
    const finalSession: TestSession = {
        ...localSession,
        endTime: Date.now(),
        isCompleted: true,
        answers: answers,
        score
    };
    setLocalSession(finalSession);
    saveSession(finalSession);
  };

  const calculateGrade = (score: number, total: number) => {
      const percentage = (score / total) * 100;
      if (percentage < 50) return { text: t.failed, color: 'text-red-600' };
      if (percentage < 60) return { text: t.passed, color: 'text-orange-600' };
      if (percentage < 75) return { text: t.good, color: 'text-yellow-600' };
      if (percentage < 90) return { text: t.veryGood, color: 'text-blue-600' };
      return { text: t.excellent, color: 'text-green-600' };
  };

  const question = localSession.questions[currentQIndex];

  if (isFinished) {
      const grade = calculateGrade(localSession.score, localSession.totalQuestions);
      return (
          <div className="bg-white p-8 rounded-lg shadow-lg text-center max-w-2xl mx-auto">
              <CheckCircle className="mx-auto text-green-500 mb-4" size={64} />
              <h2 className="text-3xl font-bold text-navy-900 mb-2">{t.congrats}</h2>
              <p className="text-gray-600 mb-6">{t.completed}</p>
              
              <div className="grid grid-cols-2 gap-4 mb-8">
                  <div className="bg-gray-100 p-4 rounded">
                      <p className="text-sm text-gray-500">{t.score}</p>
                      <p className="text-2xl font-bold">{localSession.score} / {localSession.totalQuestions}</p>
                  </div>
                  <div className="bg-gray-100 p-4 rounded">
                      <p className="text-sm text-gray-500">{t.status}</p>
                      <p className={`text-2xl font-bold ${grade.color}`}>{grade.text}</p>
                  </div>
              </div>

              <button 
                onClick={onExit}
                className="bg-navy-900 text-white px-6 py-3 rounded hover:bg-navy-800 w-full"
              >
                  {t.myTests}
              </button>
          </div>
      );
  }

  return (
    <div className="flex flex-col h-[calc(100vh-140px)]">
      {/* Top Bar */}
      <div className="bg-white p-4 rounded shadow-sm mb-4 flex justify-between items-center">
        <div className="flex items-center space-x-2 text-navy-900 font-bold">
            <Clock size={20} />
            <span className={`${timeLeft < 300 ? 'text-red-600 animate-pulse' : ''}`}>
                {t.timeLeft}: {formatTime(timeLeft)}
            </span>
        </div>
        <div className="text-gray-600">
            {t.question} {currentQIndex + 1} / {localSession.questions.length}
        </div>
      </div>

      {/* Question Area */}
      <div className="flex-1 bg-white p-6 rounded shadow-md overflow-y-auto">
         <h3 className="text-lg md:text-xl font-semibold mb-6 text-navy-900">
             {question.text}
         </h3>

         {question.image && (
             <div className="mb-6">
                 {/* Updated image path to relative */}
                 <img src={`images/${question.image}`} alt="Question diagram" className="max-h-64 rounded border" onError={(e) => (e.currentTarget.style.display = 'none')} />
                 <p className="text-xs text-gray-400 mt-1">Image: {question.image}</p>
             </div>
         )}

         <div className="space-y-3">
             {['A', 'B', 'C', 'D'].map((optKey) => {
                 const text = (question as any)[`option${optKey}`];
                 if (!text) return null;
                 const isSelected = answers[question.id] === optKey;
                 return (
                     <div 
                        key={optKey}
                        onClick={() => handleAnswer(optKey)}
                        className={`p-4 border rounded cursor-pointer transition-all flex items-start space-x-3
                            ${isSelected ? 'bg-navy-50 border-navy-500 ring-1 ring-navy-500' : 'hover:bg-gray-50 border-gray-200'}
                        `}
                     >
                         <div className={`w-6 h-6 rounded-full border flex items-center justify-center flex-shrink-0 
                             ${isSelected ? 'bg-navy-900 text-white border-navy-900' : 'text-gray-500 border-gray-300'}
                         `}>
                             {optKey}
                         </div>
                         <span className="text-gray-800 mt-0.5">{text}</span>
                     </div>
                 )
             })}
         </div>
      </div>

      {/* Footer Navigation */}
      <div className="mt-4 flex justify-between">
          <button 
            disabled={currentQIndex === 0}
            onClick={() => setCurrentQIndex(prev => prev - 1)}
            className="px-4 py-2 bg-white border border-gray-300 rounded text-gray-700 disabled:opacity-50 hover:bg-gray-50"
          >
              {t.prev}
          </button>
          
          {currentQIndex === localSession.questions.length - 1 ? (
              <button 
                onClick={finishTest}
                className="px-6 py-2 bg-gold-500 text-white font-bold rounded hover:bg-gold-600 shadow-md"
              >
                  {t.submit}
              </button>
          ) : (
            <button 
                onClick={() => setCurrentQIndex(prev => prev + 1)}
                className="px-4 py-2 bg-navy-900 text-white rounded hover:bg-navy-800"
            >
                {t.next}
            </button>
          )}
      </div>
    </div>
  );
};

export default ExamView;
