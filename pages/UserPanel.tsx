
import React, { useState, useEffect } from 'react';
import { useLanguage } from '../App';
import { TRANSLATIONS } from '../constants';
import { getCurrentUser, fetchInitialData, requestAccess, generateTest } from '../services/storageService';
import { User, Category, TestSession, Package } from '../types';
import { ShoppingCart, CheckCircle, Clock, PlayCircle, Package as PackageIcon, Info, Calendar, Wifi } from 'lucide-react';
import ExamView from '../components/ExamView';

const UserPanel: React.FC<{ view: 'functions' | 'my-tests' }> = ({ view }) => {
  const { lang } = useLanguage();
  const t = TRANSLATIONS[lang];
  const [user, setUser] = useState<User | null>(getCurrentUser());
  const [categories, setCategories] = useState<Category[]>([]);
  const [packages, setPackages] = useState<Package[]>([]);
  const [settings, setSettings] = useState<any>({});
  
  const [selectedCats, setSelectedCats] = useState<string[]>([]);
  const [selectedPkgs, setSelectedPkgs] = useState<string[]>([]);
  const [showPayment, setShowPayment] = useState(false);
  
  const [activeSession, setActiveSession] = useState<TestSession | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const load = async () => {
        const data = await fetchInitialData();
        setCategories(data.categories);
        setPackages(data.packages);
        setSettings(data.settings);
        setUser(getCurrentUser()); // update from local storage or session
    };
    load();
  }, [view]);

  const toggleCatSelection = (id: string) => {
    if (selectedCats.includes(id)) setSelectedCats(selectedCats.filter(c => c !== id));
    else setSelectedCats([...selectedCats, id]);
  };

  const togglePkgSelection = (id: string) => {
      if (selectedPkgs.includes(id)) setSelectedPkgs(selectedPkgs.filter(p => p !== id));
      else setSelectedPkgs([...selectedPkgs, id]);
  };

  const handleRequestAccess = async () => {
    if (!user) return;
    
    let allRequestedCats = [...selectedCats];
    selectedPkgs.forEach(pkgId => {
        const pkg = packages.find(p => p.id === pkgId);
        if (pkg) allRequestedCats.push(...pkg.categoryIds);
    });

    allRequestedCats = [...new Set(allRequestedCats)];
    
    try {
        await requestAccess(user.id, allRequestedCats);
        alert('Request sent! Please follow payment instructions.');
        // Optimistic update locally
        const u = { ...user, requestedCategories: [...user.requestedCategories, ...allRequestedCats] };
        setUser(u);
    } catch(e) {
        alert("Failed to send request");
    }

    setSelectedCats([]);
    setSelectedPkgs([]);
    setShowPayment(false);
  };

  const startTest = async (catId: string) => {
    if (!user) return;
    setLoading(true);

    try {
        const session = await generateTest(catId, user.id);
        setActiveSession(session);
    } catch (e: any) {
        alert("Error generating test: " + e.message);
    } finally {
        setLoading(false);
    }
  };

  if (activeSession) {
      return <ExamView session={activeSession} onExit={() => setActiveSession(null)} />;
  }

  const renderFunctions = () => {
    let total = 0;
    selectedPkgs.forEach(pid => { const p = packages.find(pkg => pkg.id === pid); if (p) total += p.price; });
    selectedCats.forEach(cid => { const c = categories.find(cat => cat.id === cid); if (c) total += c.price; });

    return (
      <div className="space-y-8 pb-24">
        {packages.length > 0 && (
            <div>
                <h3 className="text-xl font-bold text-navy-900 mb-4 flex items-center"><PackageIcon className="mr-2"/> {t.bundle}</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {packages.map(pkg => {
                        const isSelected = selectedPkgs.includes(pkg.id);
                        const allOwned = pkg.categoryIds.every(cid => user?.approvedCategories.includes(cid));
                        
                        return (
                            <div key={pkg.id} 
                                onClick={() => !allOwned && togglePkgSelection(pkg.id)}
                                className={`border-2 rounded-lg p-4 cursor-pointer transition relative
                                    ${allOwned ? 'bg-green-50 border-green-200 cursor-default' : 
                                      isSelected ? 'bg-gold-50 border-gold-500 ring-1 ring-gold-500' : 'bg-white border-navy-200 hover:border-navy-400'}
                                `}
                            >
                                <div className="flex justify-between items-start">
                                    <h4 className="font-bold text-lg text-navy-900">{lang === 'bg' ? pkg.nameBg : pkg.nameEn}</h4>
                                    {allOwned && <CheckCircle className="text-green-600" />}
                                    {!allOwned && isSelected && <CheckCircle className="text-gold-600" />}
                                </div>
                                <div className="mt-2 text-sm text-gray-600">
                                    <p className="font-semibold">{t.packageIncludes}</p>
                                    <p className="line-clamp-2 italic">
                                        {pkg.categoryIds.map(cid => {
                                            const c = categories.find(cat => cat.id === cid);
                                            return c ? (lang === 'bg' ? c.nameBg : c.nameEn) : '';
                                        }).join(', ')}
                                    </p>
                                    <p className="mt-2 text-xs text-navy-600 font-bold">Valid for: {pkg.durationDays} days</p>
                                </div>
                                <div className="mt-4 text-right">
                                    <span className="text-xl font-bold text-gold-600">{pkg.price} €</span>
                                </div>
                            </div>
                        )
                    })}
                </div>
            </div>
        )}

        <div>
            <h3 className="text-xl font-bold text-navy-900 mb-4">{t.individual}</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {categories.map(cat => {
                const isApproved = user?.approvedCategories.includes(cat.id);
                const isRequested = user?.requestedCategories.includes(cat.id);
                const isSelected = selectedCats.includes(cat.id);

                return (
                <div key={cat.id} 
                    className={`p-4 rounded-lg border-2 transition-all cursor-pointer relative shadow-sm flex flex-col justify-between
                    ${isApproved ? 'border-green-200 bg-green-50 cursor-default' : 
                        isRequested ? 'border-yellow-200 bg-yellow-50 cursor-default' :
                        isSelected ? 'border-gold-500 bg-white ring-2 ring-gold-100' : 'border-gray-200 bg-white hover:border-navy-300'}
                    `}
                    onClick={() => !isApproved && !isRequested && toggleCatSelection(cat.id)}
                >
                    <div>
                        <div className="flex justify-between items-start">
                            <h3 className="font-bold text-navy-900 pr-6 text-sm md:text-base">{lang === 'bg' ? cat.nameBg : cat.nameEn}</h3>
                            {isApproved && <CheckCircle className="text-green-600 flex-shrink-0" size={20} />}
                            {isRequested && <Clock className="text-yellow-600 flex-shrink-0" size={20} />}
                        </div>
                    </div>
                    <div className="mt-4">
                         <div className="flex justify-between text-xs text-gray-500 mb-1">
                             <span>Validity: 6 days</span>
                             <span>Exam time: {cat.durationMinutes} min</span>
                         </div>
                        <div className="text-right">
                             <span className="font-bold text-lg">{cat.price} €</span>
                        </div>
                    </div>
                </div>
                );
            })}
            </div>
        </div>

        {(selectedCats.length > 0 || selectedPkgs.length > 0) && (
            <div className="fixed bottom-6 right-6 z-40 animate-bounce-slow">
                <button onClick={() => setShowPayment(true)} className="bg-navy-900 text-white px-6 py-4 rounded-full shadow-xl flex items-center space-x-3 hover:bg-navy-800 transition transform hover:scale-105">
                    <ShoppingCart />
                    <span className="font-bold">{t.totalPrice}: {total} €</span>
                </button>
            </div>
        )}

        {showPayment && (
            <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                <div className="bg-white rounded-lg max-w-md w-full p-6 shadow-2xl relative">
                    <button onClick={() => setShowPayment(false)} className="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><span className="text-2xl">&times;</span></button>
                    <h3 className="text-xl font-bold mb-4 text-navy-900">{t.requestAccess}</h3>
                    <p className="mb-4 text-gray-600 text-sm">{t.revolutMsg}</p>
                    <div className="bg-gray-100 p-6 rounded mb-6 text-center border border-gray-200">
                        <a href={settings.revolutLink} target="_blank" rel="noreferrer" className="text-blue-600 underline font-bold text-lg break-all">
                            {settings.revolutLink}
                        </a>
                        <div className="mt-4 pt-4 border-t border-gray-300">
                            <p className="text-sm text-gray-500">{t.totalPrice}</p>
                            <p className="text-3xl font-bold text-navy-900">{total} €</p>
                        </div>
                    </div>
                    <div className="flex justify-end space-x-3">
                        <button onClick={() => setShowPayment(false)} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">{t.cancel}</button>
                        <button onClick={handleRequestAccess} className="px-6 py-2 bg-gold-500 text-white font-bold rounded hover:bg-gold-600 shadow-md">{t.send}</button>
                    </div>
                </div>
            </div>
        )}
      </div>
    );
  };

  const renderMyTests = () => {
      const myCatIds = user?.approvedCategories || [];
      const myCats = categories.filter(c => myCatIds.includes(c.id));

      if (myCats.length === 0) {
          return (
            <div className="flex flex-col items-center justify-center h-64 text-gray-500 bg-white rounded shadow-sm p-6 text-center">
                <Info size={48} className="mb-4 text-gray-300"/>
                <p>{t.noTests}</p>
            </div>
          );
      }

      return (
          <div className="space-y-6">
              <h2 className="text-2xl font-bold text-navy-900">{t.myTests}</h2>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {myCats.map(cat => {
                      const expiry = user?.categoryExpiry?.[cat.id];
                      const isExpired = expiry && Date.now() > expiry;
                      
                      return (
                        <div key={cat.id} className={`bg-white border rounded-lg p-6 shadow-sm hover:shadow-md transition relative ${isExpired ? 'border-red-200' : 'border-gray-200'}`}>
                            {isExpired && <div className="absolute top-0 right-0 bg-red-100 text-red-600 text-xs px-2 py-1 rounded-bl-lg font-bold">EXPIRED</div>}
                            <h3 className={`font-bold text-lg mb-2 ${isExpired ? 'text-gray-400' : 'text-navy-800'}`}>
                                {lang === 'bg' ? cat.nameBg : cat.nameEn}
                            </h3>
                            <div className="flex flex-col space-y-1 mb-4 text-sm text-gray-500">
                                <div className="flex justify-between">
                                    <span className="flex items-center"><Wifi className="w-3 h-3 mr-1 text-blue-500"/> Online</span>
                                    <span>Exam Time: {cat.durationMinutes} min</span>
                                </div>
                                {expiry && (
                                    <div className={`flex items-center text-xs ${isExpired ? 'text-red-500' : 'text-green-600'}`}>
                                        <Calendar size={12} className="mr-1"/>
                                        {t.expiresOn}: {new Date(expiry).toLocaleDateString()}
                                    </div>
                                )}
                            </div>
                            <button 
                                onClick={() => startTest(cat.id)}
                                disabled={isExpired || loading}
                                className={`w-full py-3 rounded flex items-center justify-center space-x-2 font-semibold transition-colors
                                    ${isExpired ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-navy-900 text-white hover:bg-navy-800'}
                                `}
                            >
                                {loading ? <span className="animate-pulse">Connecting...</span> : <><PlayCircle size={18} /><span>{isExpired ? t.accessExpired : t.generateTest}</span></>}
                            </button>
                        </div>
                      )
                  })}
              </div>
          </div>
      );
  };

  return view === 'functions' ? renderFunctions() : renderMyTests();
};

export default UserPanel;
