function timer(y, champErreur, aGriser) {
  setTimeout(() => {
    champErreur.innerHTML = "<strong>Erreur :</strong> Veuillez réessayer dans " + y +" secondes";
    if (y > 0){
        timer(y-1, champErreur, aGriser);
    }else{
        champErreur.innerHTML = "";
        aGriser.disabled = "";
    }
  }, 1000);
}