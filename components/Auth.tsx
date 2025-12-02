
import React, { useState } from 'react';
import { useLanguage } from '../App';
import { TRANSLATIONS } from '../constants';
import { loginUser, registerUser, setCurrentUser } from '../services/storageService';
import { User } from '../types';

interface AuthProps {
  onSuccess: (user: User) => void;
}

const Auth: React.FC<AuthProps> = ({ onSuccess }) => {
  const { lang } = useLanguage();
  const t = TRANSLATIONS[lang];
  const [isLogin, setIsLogin] = useState(true);
  
  const [email, setEmail] = useState('');
  const [pass, setPass] = useState('');
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
        let user: User;
        if (isLogin) {
            user = await loginUser(email, pass);
        } else {
            user = await registerUser({ email, password: pass, firstName, lastName });
        }
        setCurrentUser(user);
        onSuccess(user);
    } catch (err: any) {
        setError(err.message || 'Authentication failed');
    } finally {
        setLoading(false);
    }
  };

  return (
    <div className="flex justify-center items-center min-h-[80vh]">
      <div className="bg-white p-8 rounded-lg shadow-xl w-full max-w-md border-t-4 border-gold-500">
        <h2 className="text-2xl font-bold text-navy-900 mb-6 text-center">
          {isLogin ? t.login : t.register}
        </h2>
        
        {error && <div className="bg-red-100 text-red-700 p-2 rounded mb-4 text-sm">{error}</div>}

        <form onSubmit={handleSubmit} className="space-y-4">
          {!isLogin && (
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="block text-sm font-medium text-gray-700">{t.firstName}</label>
                <input required type="text" className="mt-1 block w-full border border-gray-300 rounded-md p-2" value={firstName} onChange={e => setFirstName(e.target.value)} />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">{t.lastName}</label>
                <input required type="text" className="mt-1 block w-full border border-gray-300 rounded-md p-2" value={lastName} onChange={e => setLastName(e.target.value)} />
              </div>
            </div>
          )}
          
          <div>
            <label className="block text-sm font-medium text-gray-700">{t.email}</label>
            <input required type="email" className="mt-1 block w-full border border-gray-300 rounded-md p-2" value={email} onChange={e => setEmail(e.target.value)} />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700">{t.password}</label>
            <input required type="password" className="mt-1 block w-full border border-gray-300 rounded-md p-2" value={pass} onChange={e => setPass(e.target.value)} />
          </div>

          <button disabled={loading} type="submit" className="w-full bg-navy-900 text-white py-2 rounded-md hover:bg-navy-800 transition font-bold disabled:opacity-50">
            {loading ? 'Processing...' : (isLogin ? t.login : t.register)}
          </button>
        </form>

        <p className="mt-4 text-center text-sm text-gray-600">
          {isLogin ? "New here? " : "Already have an account? "}
          <span 
            className="text-gold-600 cursor-pointer font-bold hover:underline"
            onClick={() => setIsLogin(!isLogin)}
          >
            {isLogin ? t.register : t.login}
          </span>
        </p>
      </div>
    </div>
  );
};

export default Auth;
