
import React, { useState, useEffect } from 'react';
import { useLanguage } from '../App';
import { TRANSLATIONS } from '../constants';
import { User, Role, GlobalSettings } from '../types';
import { LogOut, Menu, UserCircle, Settings, BookOpen, ShoppingBag, PieChart, X } from 'lucide-react';
import { setCurrentUser, fetchInitialData } from '../services/storageService';

interface LayoutProps {
  children: React.ReactNode;
  user: User | null;
  setUser: (u: User | null) => void;
}

const Layout: React.FC<LayoutProps> = ({ children, user, setUser }) => {
  const { lang, setLang } = useLanguage();
  const t = TRANSLATIONS[lang];
  const [mobileMenuOpen, setMobileMenuOpen] = React.useState(false);
  
  const [showAnnouncement, setShowAnnouncement] = useState(true);
  const [settings, setSettings] = useState<GlobalSettings | null>(null);

  useEffect(() => {
    const loadSettings = async () => {
        try {
            const data = await fetchInitialData();
            if (data.settings) setSettings(data.settings);
        } catch (e) { console.error(e); }
    };
    loadSettings();

    const handleUpdate = () => loadSettings();
    window.addEventListener('maritime_settings_updated', handleUpdate);
    return () => window.removeEventListener('maritime_settings_updated', handleUpdate);
  }, []);

  const handleLogout = () => {
    setCurrentUser(null);
    setUser(null);
    window.location.hash = '#/';
  };

  const NavItem = ({ to, icon: Icon, label }: { to: string; icon: any; label: string }) => (
    <div 
      onClick={() => {
        window.location.hash = to;
        setMobileMenuOpen(false);
      }}
      className="flex items-center space-x-2 px-4 py-2 text-navy-800 hover:bg-blue-50 cursor-pointer rounded-md transition-colors"
    >
      <Icon size={20} />
      <span>{label}</span>
    </div>
  );

  return (
    <div className="min-h-screen flex flex-col bg-gray-50 font-sans">
      
      {showAnnouncement && settings?.announcement && (
        <div className="bg-yellow-400 text-navy-900 px-4 py-2 text-sm font-medium flex justify-between items-center shadow-sm relative z-50">
            <div className="flex-1 text-center mx-4">{settings.announcement}</div>
            <button onClick={() => setShowAnnouncement(false)} className="text-navy-900 hover:text-black"><X size={18} /></button>
        </div>
      )}

      {/* Header - Increased height to accommodate logo */}
      <header className="bg-navy-900 text-white sticky top-0 z-40 shadow-lg h-[80px]">
        <div className="container mx-auto px-4 h-full flex items-center justify-between">
          <div className="flex items-center cursor-pointer" onClick={() => window.location.hash = '#/'}>
            {/* Logo Image - Use static path assuming images/ is in root */}
            <img 
              src="images/logoM.png" 
              alt="Maritime Logo" 
              className="w-[50px] h-[54px] sm:w-[65px] sm:h-[70px] object-contain"
            />
          </div>

          <div className="flex items-center space-x-4">
             <button 
              onClick={() => setLang(lang === 'bg' ? 'en' : 'bg')}
              className="text-sm font-semibold bg-navy-800 px-3 py-1 rounded border border-navy-700 hover:border-gold-500 transition-colors"
            >
              {lang.toUpperCase()}
            </button>

            {user ? (
              <div className="hidden md:flex items-center space-x-4">
                <span>{user.firstName} {user.lastName}</span>
                <button onClick={handleLogout} className="text-gray-300 hover:text-white">
                  <LogOut size={20} />
                </button>
              </div>
            ) : null}

            <button className="md:hidden" onClick={() => setMobileMenuOpen(!mobileMenuOpen)}>
              <Menu size={24} />
            </button>
          </div>
        </div>
      </header>

      {mobileMenuOpen && (
        <div className="md:hidden bg-white border-b shadow-md py-2">
            {user && (
              <>
                 <div className="px-4 py-2 font-bold text-navy-900 border-b mb-2">{user.firstName}</div>
                 {user.role === Role.ADMIN ? (
                    <NavItem to="#/admin" icon={Settings} label={t.adminPanel} />
                 ) : (
                   <>
                     <NavItem to="#/" icon={PieChart} label={t.home} />
                     <NavItem to="#/functions" icon={ShoppingBag} label={t.functions} />
                     <NavItem to="#/my-tests" icon={BookOpen} label={t.myTests} />
                   </>
                 )}
                 <div onClick={handleLogout} className="flex items-center space-x-2 px-4 py-2 text-red-600 hover:bg-red-50 cursor-pointer">
                    <LogOut size={20} />
                    <span>{t.logout}</span>
                 </div>
              </>
            )}
        </div>
      )}

      <div className="flex flex-1 container mx-auto px-4 py-6 gap-6">
        {user && (
            <aside className="hidden md:block w-64 bg-white shadow-md rounded-lg h-fit p-4 sticky top-28">
                <nav className="space-y-2">
                   {user.role === Role.ADMIN ? (
                     <NavItem to="#/admin" icon={Settings} label={t.adminPanel} />
                   ) : (
                     <>
                        <NavItem to="#/" icon={PieChart} label={t.home} />
                        <NavItem to="#/functions" icon={ShoppingBag} label={t.functions} />
                        <NavItem to="#/my-tests" icon={BookOpen} label={t.myTests} />
                        <NavItem to="#/contact" icon={UserCircle} label={t.contact} />
                     </>
                   )}
                </nav>
            </aside>
        )}

        <main className={`flex-1 ${!user ? 'w-full' : ''}`}>
          {children}
        </main>
      </div>
    </div>
  );
};

export default Layout;
