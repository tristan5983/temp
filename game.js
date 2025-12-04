import React, { useState } from 'react';
import { Sparkles, TrendingUp, Zap, Crown, Dices, Heart, Spade, Diamond, Club } from 'lucide-react';

const CasinoLobby = () => {
  const [balance, setBalance] = useState(1000);
  const [selectedCategory, setSelectedCategory] = useState('all');

  const games = [
    {
      id: 1,
      name: "Lucky Sevens",
      category: "slots",
      image: "🎰",
      minBet: 1,
      maxBet: 100,
      hot: true,
      players: 234
    },
    {
      id: 2,
      name: "Diamond Rush",
      category: "slots",
      image: "💎",
      minBet: 5,
      maxBet: 500,
      hot: true,
      players: 189
    },
    {
      id: 3,
      name: "Blackjack Classic",
      category: "table",
      image: "🃏",
      minBet: 10,
      maxBet: 1000,
      hot: false,
      players: 156
    },
    {
      id: 4,
      name: "Roulette Royale",
      category: "table",
      image: "🎡",
      minBet: 5,
      maxBet: 500,
      hot: false,
      players: 178
    },
    {
      id: 5,
      name: "Fruit Blast",
      category: "slots",
      image: "🍒",
      minBet: 1,
      maxBet: 50,
      hot: false,
      players: 98
    },
    {
      id: 6,
      name: "Poker Palace",
      category: "poker",
      image: "♠️",
      minBet: 20,
      maxBet: 2000,
      hot: true,
      players: 267
    },
    {
      id: 7,
      name: "Golden Pharaoh",
      category: "slots",
      image: "👑",
      minBet: 2,
      maxBet: 200,
      hot: false,
      players: 143
    },
    {
      id: 8,
      name: "Baccarat Elite",
      category: "table",
      image: "💰",
      minBet: 25,
      maxBet: 5000,
      hot: false,
      players: 87
    }
  ];

  const categories = [
    { id: 'all', name: 'All Games', icon: Sparkles },
    { id: 'slots', name: 'Slots', icon: Zap },
    { id: 'table', name: 'Table Games', icon: Diamond },
    { id: 'poker', name: 'Poker', icon: Spade }
  ];

  const filteredGames = selectedCategory === 'all' 
    ? games 
    : games.filter(game => game.category === selectedCategory);

  const handleGameClick = (game) => {
    alert(`Launching ${game.name}...\n\nThis would navigate to the game screen.\nMin Bet: $${game.minBet} | Max Bet: $${game.maxBet}`);
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-purple-900 via-indigo-900 to-blue-900 text-white">
      {/* Header */}
      <div className="bg-black/30 backdrop-blur-sm border-b border-white/10 sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-4 py-4">
          <div className="flex justify-between items-center">
            <div className="flex items-center gap-2">
              <Crown className="w-8 h-8 text-yellow-400" />
              <h1 className="text-2xl font-bold bg-gradient-to-r from-yellow-400 to-orange-500 bg-clip-text text-transparent">
                Royal Casino
              </h1>
            </div>
            <div className="flex items-center gap-4">
              <div className="bg-gradient-to-r from-yellow-500 to-orange-500 px-4 py-2 rounded-full font-bold shadow-lg">
                ${balance.toLocaleString()}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Hero Banner */}
      <div className="relative overflow-hidden bg-gradient-to-r from-pink-600 via-purple-600 to-indigo-600 mx-4 mt-4 rounded-2xl shadow-2xl">
        <div className="absolute inset-0 bg-black/20"></div>
        <div className="relative px-6 py-8">
          <div className="flex items-center gap-2 mb-2">
            <Sparkles className="w-5 h-5 text-yellow-300 animate-pulse" />
            <span className="text-yellow-300 font-semibold text-sm uppercase tracking-wide">
              Welcome Bonus
            </span>
          </div>
          <h2 className="text-3xl font-bold mb-2">Get 200% Match</h2>
          <p className="text-white/90 mb-4">Up to $2,000 on your first deposit</p>
          <button className="bg-white text-purple-900 px-6 py-2 rounded-full font-bold hover:bg-yellow-300 transition-all transform hover:scale-105">
            Claim Now
          </button>
        </div>
        <div className="absolute -right-8 -bottom-8 text-9xl opacity-20">🎰</div>
      </div>

      {/* Category Filter */}
      <div className="px-4 mt-6">
        <div className="flex gap-2 overflow-x-auto pb-2 hide-scrollbar">
          {categories.map((cat) => {
            const Icon = cat.icon;
            return (
              <button
                key={cat.id}
                onClick={() => setSelectedCategory(cat.id)}
                className={`flex items-center gap-2 px-4 py-2 rounded-full font-semibold whitespace-nowrap transition-all ${
                  selectedCategory === cat.id
                    ? 'bg-gradient-to-r from-yellow-500 to-orange-500 text-white shadow-lg scale-105'
                    : 'bg-white/10 text-white/70 hover:bg-white/20'
                }`}
              >
                <Icon className="w-4 h-4" />
                {cat.name}
              </button>
            );
          })}
        </div>
      </div>

      {/* Hot Games Section */}
      {selectedCategory === 'all' && (
        <div className="px-4 mt-6">
          <div className="flex items-center gap-2 mb-3">
            <TrendingUp className="w-5 h-5 text-orange-400" />
            <h3 className="text-xl font-bold">🔥 Hot Games</h3>
          </div>
          <div className="flex gap-3 overflow-x-auto pb-2 hide-scrollbar">
            {games.filter(g => g.hot).map((game) => (
              <div
                key={game.id}
                onClick={() => handleGameClick(game)}
                className="flex-shrink-0 w-40 bg-gradient-to-br from-orange-500/20 to-red-500/20 backdrop-blur-sm border border-orange-500/30 rounded-xl p-4 cursor-pointer hover:scale-105 transition-transform"
              >
                <div className="text-5xl mb-2">{game.image}</div>
                <h4 className="font-bold text-sm mb-1">{game.name}</h4>
                <div className="flex items-center gap-1 text-xs text-white/70">
                  <span>{game.players} playing</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* All Games Grid */}
      <div className="px-4 mt-6 pb-20">
        <h3 className="text-xl font-bold mb-3">
          {selectedCategory === 'all' ? 'All Games' : categories.find(c => c.id === selectedCategory)?.name}
        </h3>
        <div className="grid grid-cols-2 gap-3">
          {filteredGames.map((game) => (
            <div
              key={game.id}
              onClick={() => handleGameClick(game)}
              className="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl overflow-hidden cursor-pointer hover:scale-105 hover:border-yellow-500/50 transition-all"
            >
              <div className="relative bg-gradient-to-br from-purple-600/30 to-indigo-600/30 p-8 flex items-center justify-center">
                <div className="text-6xl">{game.image}</div>
                {game.hot && (
                  <div className="absolute top-2 right-2 bg-gradient-to-r from-orange-500 to-red-500 text-white text-xs px-2 py-1 rounded-full font-bold flex items-center gap-1">
                    🔥 HOT
                  </div>
                )}
              </div>
              <div className="p-3">
                <h4 className="font-bold mb-1">{game.name}</h4>
                <div className="flex justify-between items-center text-xs text-white/60">
                  <span>${game.minBet}-${game.maxBet}</span>
                  <span className="flex items-center gap-1">
                    <div className="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                    {game.players}
                  </span>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Bottom Navigation */}
      <div className="fixed bottom-0 left-0 right-0 bg-black/40 backdrop-blur-lg border-t border-white/10">
        <div className="flex justify-around items-center py-3 px-4">
          <button className="flex flex-col items-center gap-1 text-yellow-400">
            <Dices className="w-6 h-6" />
            <span className="text-xs font-semibold">Games</span>
          </button>
          <button className="flex flex-col items-center gap-1 text-white/50 hover:text-white/80">
            <Heart className="w-6 h-6" />
            <span className="text-xs">Favorites</span>
          </button>
          <button className="flex flex-col items-center gap-1 text-white/50 hover:text-white/80">
            <TrendingUp className="w-6 h-6" />
            <span className="text-xs">Rewards</span>
          </button>
          <button className="flex flex-col items-center gap-1 text-white/50 hover:text-white/80">
            <Crown className="w-6 h-6" />
            <span className="text-xs">VIP</span>
          </button>
        </div>
      </div>

      <style jsx>{`
        .hide-scrollbar {
          -ms-overflow-style: none;
          scrollbar-width: none;
        }
        .hide-scrollbar::-webkit-scrollbar {
          display: none;
        }
      `}</style>
    </div>
  );
};

export default CasinoLobby;
