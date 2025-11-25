import os
import requests
import re
import time

# --- 1. VOS DONNÉES (La liste complète nettoyée) ---

csv_data = """
Smartphone XPro;https://images.pexels.com/photos/1092644/pexels-photo-1092644.jpeg?auto=compress&cs=tinysrgb&w=600
Ordinateur portable Ultra;https://images.pexels.com/photos/18105/pexels-photo.jpg?auto=compress&cs=tinysrgb&w=600
T-shirt coton bio;https://images.pexels.com/photos/428338/pexels-photo-428338.jpeg?auto=compress&cs=tinysrgb&w=600
Jean slim;https://images.pexels.com/photos/1082529/pexels-photo-1082529.jpeg?auto=compress&cs=tinysrgb&w=600
Robe d été;https://images.pexels.com/photos/985635/pexels-photo-985635.jpeg?auto=compress&cs=tinysrgb&w=600
Casque audio Bluetooth;https://images.pexels.com/photos/1649771/pexels-photo-1649771.jpeg?auto=compress&cs=tinysrgb&w=600
Sac à dos urbain;https://images.pexels.com/photos/2905238/pexels-photo-2905238.jpeg?auto=compress&cs=tinysrgb&w=600
Montre connectée;https://images.pexels.com/photos/267394/pexels-photo-267394.jpeg?auto=compress&cs=tinysrgb&w=600
Chaussures running;https://images.pexels.com/photos/2529148/pexels-photo-2529148.jpeg?auto=compress&cs=tinysrgb&w=600
Tablette 10 pouces;https://images.pexels.com/photos/1334597/pexels-photo-1334597.jpeg?auto=compress&cs=tinysrgb&w=600
Ballon de football;https://images.pexels.com/photos/47730/the-ball-stadion-football-the-pitch-47730.jpeg?auto=compress&cs=tinysrgb&w=600
Veste en cuir;https://images.pexels.com/photos/1124468/pexels-photo-1124468.jpeg?auto=compress&cs=tinysrgb&w=600
Livre cuisine française;https://images.pexels.com/photos/46274/pexels-photo-46274.jpeg?auto=compress&cs=tinysrgb&w=600
Tapis de yoga;https://images.pexels.com/photos/4056535/pexels-photo-4056535.jpeg?auto=compress&cs=tinysrgb&w=600
Clavier mécanique RGB;https://images.pexels.com/photos/1779487/pexels-photo-1779487.jpeg?auto=compress&cs=tinysrgb&w=600
Console NextGen;https://images.pexels.com/photos/4219883/pexels-photo-4219883.jpeg?auto=compress&cs=tinysrgb&w=600
Sweat à capuche premium;https://images.pexels.com/photos/6311392/pexels-photo-6311392.jpeg?auto=compress&cs=tinysrgb&w=600
Raquette de tennis pro;https://images.pexels.com/photos/5739161/pexels-photo-5739161.jpeg?auto=compress&cs=tinysrgb&w=600
Écran gaming 4K 27";https://images.pexels.com/photos/777001/pexels-photo-777001.jpeg?auto=compress&cs=tinysrgb&w=600
Baskets limited edition;https://images.pexels.com/photos/2529148/pexels-photo-2529148.jpeg?auto=compress&cs=tinysrgb&w=600
Vélo de route carbone;https://images.pexels.com/photos/100582/pexels-photo-100582.jpeg?auto=compress&cs=tinysrgb&w=600
Drone professionnel 4K;https://images.pexels.com/photos/724921/pexels-photo-724921.jpeg?auto=compress&cs=tinysrgb&w=600
Manteau d hiver imperméable;https://images.pexels.com/photos/3965543/pexels-photo-3965543.jpeg?auto=compress&cs=tinysrgb&w=600
Tente 4 places;https://images.pexels.com/photos/2422265/pexels-photo-2422265.jpeg?auto=compress&cs=tinysrgb&w=600
Enceinte Bluetooth waterproof;https://images.pexels.com/photos/1279107/pexels-photo-1279107.jpeg?auto=compress&cs=tinysrgb&w=600
Pull Marin de Saint-Malo;https://upload.wikimedia.org/wikipedia/commons/a/a8/Marin_fran%C3%A7ais_vers_1910.jpg
Bonnet Miki de Carnac;https://upload.wikimedia.org/wikipedia/commons/d/d0/Miki_Hood_pg736.jpg
Galettes Local;https://upload.wikimedia.org/wikipedia/commons/0/07/Galette_de_sarrasin.jpg
Écharpe Rayée Fait main;https://upload.wikimedia.org/wikipedia/commons/d/d6/Knitted-scarf.jpg
Écharpe Rayée de Brest;https://upload.wikimedia.org/wikipedia/commons/d/d6/Knitted-scarf.jpg
Ciré Navy de Concarneau;https://upload.wikimedia.org/wikipedia/commons/d/d3/Cir%C3%A9_jaune.jpg
Bracelet Ancre Fait main;https://upload.wikimedia.org/wikipedia/commons/8/8e/Friendship_bracelet_diagram.jpg
Marinière Local;https://upload.wikimedia.org/wikipedia/commons/a/a8/Marin_fran%C3%A7ais_vers_1910.jpg
Bracelet Ancre de Concarneau;https://upload.wikimedia.org/wikipedia/commons/8/8e/Friendship_bracelet_diagram.jpg
Pendentif Hermine Fait main;https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Ermine_%28heraldry%29.svg/400px-Ermine_%28heraldry%29.svg.png
Sac Voile de Concarneau;https://upload.wikimedia.org/wikipedia/commons/a/a3/Sac_marin.jpg
Écharpe Rayée de Saint-Malo;https://upload.wikimedia.org/wikipedia/commons/d/d6/Knitted-scarf.jpg
Coussin Triskell de Roscoff;https://upload.wikimedia.org/wikipedia/commons/thumb/5/5d/Triskele-Symbol-spiral-five-thirds-turns.png/600px-Triskele-Symbol-spiral-five-thirds-turns.png
Écharpe Rayée Authentique;https://upload.wikimedia.org/wikipedia/commons/d/d6/Knitted-scarf.jpg
Écharpe Rayée Bio;https://upload.wikimedia.org/wikipedia/commons/d/d6/Knitted-scarf.jpg
Bonnet Miki Authentique;https://upload.wikimedia.org/wikipedia/commons/d/d0/Miki_Hood_pg736.jpg
Ciré Navy de Quimper;https://upload.wikimedia.org/wikipedia/commons/d/d3/Cir%C3%A9_jaune.jpg
Bol Breton Traditionnel;https://upload.wikimedia.org/wikipedia/commons/3/34/Bol_breton.JPG
Bol Breton Premium;https://upload.wikimedia.org/wikipedia/commons/3/34/Bol_breton.JPG
Pull Marin de Brest;https://upload.wikimedia.org/wikipedia/commons/a/a8/Marin_fran%C3%A7ais_vers_1910.jpg
Affiche Vintage de Dinard;https://upload.wikimedia.org/wikipedia/commons/thumb/8/81/Poster_Quimper.jpg/400px-Poster_Quimper.jpg
Vareuse Artisanal;https://upload.wikimedia.org/wikipedia/commons/0/0f/Vareuse_bretonne_authentique.jpg
Pull Marin Bio;https://upload.wikimedia.org/wikipedia/commons/a/a8/Marin_fran%C3%A7ais_vers_1910.jpg
Kabig Bio;https://upload.wikimedia.org/wikipedia/commons/9/95/Kabig_ou_Kab_an_aod_%28_Mus%C3%A9e_de_Bretagne%29.jpg
Phare Miniature Fait main;https://upload.wikimedia.org/wikipedia/commons/thumb/3/3c/Phare_du_Petit_Minou_au_cr%C3%A9puscule.jpg/640px-Phare_du_Petit_Minou_au_cr%C3%A9puscule.jpg
Bol Breton Fait main;https://upload.wikimedia.org/wikipedia/commons/3/34/Bol_breton.JPG
Bracelet Ancre de Carnac;https://upload.wikimedia.org/wikipedia/commons/8/8e/Friendship_bracelet_diagram.jpg
Caramels de Dinard;https://upload.wikimedia.org/wikipedia/commons/5/53/Caramel_au_beurre_sal%C3%A9_03.jpg
Affiche Vintage de Vannes;https://upload.wikimedia.org/wikipedia/commons/thumb/8/81/Poster_Quimper.jpg/400px-Poster_Quimper.jpg
Écharpe Rayée de Dinard;https://upload.wikimedia.org/wikipedia/commons/d/d6/Knitted-scarf.jpg
Vareuse de Saint-Malo;https://upload.wikimedia.org/wikipedia/commons/0/0f/Vareuse_bretonne_authentique.jpg
Bracelet Ancre Durable;https://upload.wikimedia.org/wikipedia/commons/8/8e/Friendship_bracelet_diagram.jpg
Lampe Tempête de Quimper;https://upload.wikimedia.org/wikipedia/commons/3/3f/Une_lampe-temp%C3%AAte_%283513926652%29.jpg
Sac Voile de Pont-Aven;https://upload.wikimedia.org/wikipedia/commons/a/a3/Sac_marin.jpg
Caramels de Roscoff;https://upload.wikimedia.org/wikipedia/commons/5/53/Caramel_au_beurre_sal%C3%A9_03.jpg
Pendentif Hermine de Saint-Malo;https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Ermine_%28heraldry%29.svg/400px-Ermine_%28heraldry%29.svg.png
Ciré Navy Artisanal;https://upload.wikimedia.org/wikipedia/commons/d/d3/Cir%C3%A9_jaune.jpg
Vareuse de Pont-Aven;https://upload.wikimedia.org/wikipedia/commons/0/0f/Vareuse_bretonne_authentique.jpg
Pendentif Hermine Bio;https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Ermine_%28heraldry%29.svg/400px-Ermine_%28heraldry%29.svg.png
Galettes de Quimper;https://upload.wikimedia.org/wikipedia/commons/0/07/Galette_de_sarrasin.jpg
Sac Voile Durable;https://upload.wikimedia.org/wikipedia/commons/a/a3/Sac_marin.jpg
Marinière de Pont-Aven;https://upload.wikimedia.org/wikipedia/commons/a/a8/Marin_fran%C3%A7ais_vers_1910.jpg
Sac Voile Bio;https://upload.wikimedia.org/wikipedia/commons/a/a3/Sac_marin.jpg
Sac Voile Premium;https://upload.wikimedia.org/wikipedia/commons/a/a3/Sac_marin.jpg
Pendentif Hermine Local;https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Ermine_%28heraldry%29.svg/400px-Ermine_%28heraldry%29.svg.png
Vareuse Durable;https://upload.wikimedia.org/wikipedia/commons/0/0f/Vareuse_bretonne_authentique.jpg
Pendentif Hermine Traditionnel;https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Ermine_%28heraldry%29.svg/400px-Ermine_%28heraldry%29.svg.png
Pendentif Hermine de Roscoff;https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Ermine_%28heraldry%29.svg/400px-Ermine_%28heraldry%29.svg.png
Ciré Navy de Brest;https://upload.wikimedia.org/wikipedia/commons/d/d3/Cir%C3%A9_jaune.jpg
Pull Marin Authentique;https://upload.wikimedia.org/wikipedia/commons/a/a8/Marin_fran%C3%A7ais_vers_1910.jpg
Bolée à Cidre Local;https://upload.wikimedia.org/wikipedia/commons/3/34/Bol_breton.JPG
Bol Breton de Saint-Malo;https://upload.wikimedia.org/wikipedia/commons/3/34/Bol_breton.JPG
Phare Miniature de Quimper;https://upload.wikimedia.org/wikipedia/commons/thumb/3/3c/Phare_du_Petit_Minou_au_cr%C3%A9puscule.jpg/640px-Phare_du_Petit_Minou_au_cr%C3%A9puscule.jpg
Caramels Artisanal;https://upload.wikimedia.org/wikipedia/commons/5/53/Caramel_au_beurre_sal%C3%A9_03.jpg
Vareuse de Dinard;https://upload.wikimedia.org/wikipedia/commons/0/0f/Vareuse_bretonne_authentique.jpg
Lampe Tempête Traditionnel;https://upload.wikimedia.org/wikipedia/commons/3/3f/Une_lampe-temp%C3%AAte_%283513926652%29.jpg
Bracelet Ancre de Brest;https://upload.wikimedia.org/wikipedia/commons/8/8e/Friendship_bracelet_diagram.jpg
Coussin Triskell Premium;https://upload.wikimedia.org/wikipedia/commons/thumb/5/5d/Triskele-Symbol-spiral-five-thirds-turns.png/600px-Triskele-Symbol-spiral-five-thirds-turns.png
Coussin Triskell Bio;https://upload.wikimedia.org/wikipedia/commons/thumb/5/5d/Triskele-Symbol-spiral-five-thirds-turns.png/600px-Triskele-Symbol-spiral-five-thirds-turns.png
Lampe Tempête de Saint-Malo;https://upload.wikimedia.org/wikipedia/commons/3/3f/Une_lampe-temp%C3%AAte_%283513926652%29.jpg
Coussin Triskell Artisanal;https://upload.wikimedia.org/wikipedia/commons/thumb/5/5d/Triskele-Symbol-spiral-five-thirds-turns.png/600px-Triskele-Symbol-spiral-five-thirds-turns.png
Bolée à Cidre de Saint-Malo;https://upload.wikimedia.org/wikipedia/commons/3/34/Bol_breton.JPG
Caramels Durable;https://upload.wikimedia.org/wikipedia/commons/5/53/Caramel_au_beurre_sal%C3%A9_03.jpg
Lampe Tempête Local;https://upload.wikimedia.org/wikipedia/commons/3/3f/Une_lampe-temp%C3%AAte_%283513926652%29.jpg
Bolée à Cidre Artisanal;https://upload.wikimedia.org/wikipedia/commons/3/34/Bol_breton.JPG
Bonnet Miki Traditionnel;https://upload.wikimedia.org/wikipedia/commons/d/d0/Miki_Hood_pg736.jpg
Bonnet Miki Premium;https://upload.wikimedia.org/wikipedia/commons/d/d0/Miki_Hood_pg736.jpg
Caramels de Carnac;https://upload.wikimedia.org/wikipedia/commons/5/53/Caramel_au_beurre_sal%C3%A9_03.jpg
Marinière Premium;https://upload.wikimedia.org/wikipedia/commons/a/a8/Marin_fran%C3%A7ais_vers_1910.jpg
Pull Marin de Quimper;https://upload.wikimedia.org/wikipedia/commons/a/a8/Marin_fran%C3%A7ais_vers_1910.jpg
Ciré Jaune de Vannes;https://upload.wikimedia.org/wikipedia/commons/d/d3/Cir%C3%A9_jaune.jpg
Lampe Tempête Authentique;https://upload.wikimedia.org/wikipedia/commons/3/3f/Une_lampe-temp%C3%AAte_%283513926652%29.jpg
Bonnet Miki de Concarneau;https://upload.wikimedia.org/wikipedia/commons/d/d0/Miki_Hood_pg736.jpg
Marinière Authentique;https://upload.wikimedia.org/wikipedia/commons/a/a8/Marin_fran%C3%A7ais_vers_1910.jpg
Bracelet Ancre Artisanal;https://upload.wikimedia.org/wikipedia/commons/8/8e/Friendship_bracelet_diagram.jpg
Lampe Tempête de Vannes;https://upload.wikimedia.org/wikipedia/commons/3/3f/Une_lampe-temp%C3%AAte_%283513926652%29.jpg
Pull Marin de Carnac;https://upload.wikimedia.org/wikipedia/commons/a/a8/Marin_fran%C3%A7ais_vers_1910.jpg
Coussin Triskell Fait main;https://upload.wikimedia.org/wikipedia/commons/thumb/5/5d/Triskele-Symbol-spiral-five-thirds-turns.png/600px-Triskele-Symbol-spiral-five-thirds-turns.png
Galettes Artisanal;https://upload.wikimedia.org/wikipedia/commons/0/07/Galette_de_sarrasin.jpg
Bol Breton Bio;https://upload.wikimedia.org/wikipedia/commons/3/34/Bol_breton.JPG
"""

# --- 2. CONFIGURATION ---
# Headers user-agent pour éviter d'être bloqué par Wikimedia/Pexels
HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
}
OUTPUT_DIR = "images_produits"

# --- 3. FONCTION DE NETTOYAGE DU NOM (SLUG) ---
def slugify(text):
    text = text.lower()
    text = text.replace("'", "").replace("à", "a").replace("é", "e").replace("è", "e").replace("ê", "e").replace("â", "a")
    text = re.sub(r'[^a-z0-9]+', '-', text).strip('-')
    return text

# --- 4. LE SCRIPT ---
def download_images():
    if not os.path.exists(OUTPUT_DIR):
        os.makedirs(OUTPUT_DIR)
        print(f"Dossier '{OUTPUT_DIR}' créé.")

    lines = csv_data.strip().split('\n')
    print(f"Début du téléchargement de {len(lines)} images...\n")

    success_count = 0
    
    for line in lines:
        if not line or ";" not in line: continue
        
        name, url = line.split(';')
        slug = slugify(name)
        
        # Détermine l'extension (jpg ou png par défaut)
        ext = "jpg"
        if ".png" in url or ".svg" in url: # Wikimedia renvoie parfois du SVG, on le garde ou on le renomme
            if ".svg" in url: ext = "svg" # Attention, SVG n'est pas une image pixel
            else: ext = "png"
        
        filename = f"{slug}.{ext}"
        filepath = os.path.join(OUTPUT_DIR, filename)

        print(f"Téléchargement de : {name} -> {filename}")
        
        try:
            # Pour Wikimedia, il faut parfois suivre les redirections
            response = requests.get(url, headers=HEADERS, allow_redirects=True, timeout=10)
            
            if response.status_code == 200:
                with open(filepath, 'wb') as f:
                    f.write(response.content)
                success_count += 1
            else:
                print(f"   ERREUR {response.status_code} pour {url}")
                
            # Petite pause pour être poli envers le serveur
            time.sleep(0.5)
            
        except Exception as e:
            print(f"   EXCEPTION: {e}")

    print(f"\nTerminé ! {success_count}/{len(lines)} images téléchargées dans '{OUTPUT_DIR}'.")

if __name__ == "__main__":
    download_images()
