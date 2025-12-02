
import React, { useState, useEffect, createContext, useContext } from 'react';
import { getCurrentUser, fetchInitialData } from './services/storageService';
import { User, Role, Language } from './types';
import Layout from './components/Layout';
import Auth from './components/Auth';
import AdminPanel from './pages/AdminPanel';
import UserPanel from './pages/UserPanel';
import { TRANSLATIONS } from './constants';
import { Facebook } from 'lucide-react';

interface LangContextType {
  lang: Language;
  setLang: (l: Language) => void;
}
const LangContext = createContext<LangContextType>({ lang: 'bg', setLang: () => {} });
export const useLanguage = () => useContext(LangContext);

const App: React.FC = () => {
  const [user, setUser] = useState<User | null>(null);
  const [lang, setLang] = useState<Language>('bg');
  const [route, setRoute] = useState<string>('');
  const [loading, setLoading] = useState(true);
  const [fbLink, setFbLink] = useState('');
  
  const t = TRANSLATIONS[lang];

  useEffect(() => {
    // Restore Session
    const restoreSession = async () => {
        const stored = getCurrentUser();
        if (stored) setUser(stored);
        
        // Fetch Settings & Config
        try {
            const data = await fetchInitialData();
            if (data.settings?.facebookLink) setFbLink(data.settings.facebookLink);
        } catch (e) { console.error(e); }
        
        setLoading(false);
    };
    
    restoreSession();

    const handleHashChange = () => {
      const hash = window.location.hash.slice(1);
      setRoute(hash || '/');
    };

    window.addEventListener('hashchange', handleHashChange);
    handleHashChange();

    return () => window.removeEventListener('hashchange', handleHashChange);
  }, []);

  const renderContent = () => {
    if (loading) return <div className="flex h-screen items-center justify-center">Loading...</div>;

    if (!user) {
      return <Auth onSuccess={(u) => setUser(u)} />;
    }

    if (user.role === Role.ADMIN) {
      if (route === '/admin') return <AdminPanel />;
      // Default to admin panel if admin
      return <AdminPanel />; 
    }

    switch (route) {
      case '/functions': return <UserPanel view="functions" />;
      case '/my-tests': return <UserPanel view="my-tests" />;
      case '/contact': 
        return (
          <div className="p-8 bg-white rounded shadow text-center max-w-lg mx-auto mt-10">
              <h2 className="text-2xl font-bold mb-6 text-navy-900">{t.contactUs}</h2>
              <div className="space-y-4">
                 <p className="text-lg text-gray-700 font-medium">Email: <span className="font-bold">admin@maritime.com</span></p>
                 {fbLink && (
                     <a href={fbLink} target="_blank" rel="noreferrer" 
                        className="inline-flex items-center space-x-2 text-blue-600 hover:text-blue-800 transition-colors font-bold text-lg border border-blue-200 px-4 py-2 rounded-lg bg-blue-50 hover:bg-blue-100"
                     >
                         <Facebook size={24} />
                         <span>{t.followUs}</span>
                     </a>
                 )}
              </div>
          </div>
        );
      default: return (
        <div className="bg-white p-8 rounded-lg shadow-md text-center">
            <h1 className="text-3xl font-bold text-navy-900 mb-4">{lang === 'bg' ? 'Добре дошли' : 'Welcome'}</h1>
            <p className="text-gray-600 mb-6">{lang === 'bg' ? 'Изберете "Функции" за да започнете.' : 'Select "Functions" to start.'}</p>
        </div>
      );
    }
  };

  return (
    <LangContext.Provider value={{ lang, setLang }}>
      <Layout user={user} setUser={setUser}>
        {renderContent()}
      </Layout>
    </LangContext.Provider>
  );
};

export default App;
