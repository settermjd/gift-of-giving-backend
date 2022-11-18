<template>
  <h2 class="mb-3">Donate</h2>
  <form id="donation-form" class="donate-wrapper" action="/donation" method="post">
    <div class="p-4 w-full mt-auto">
      <div class="mb-4 flex flex-row">
        <h3 class="mr-3 mt-2">To:</h3>
        <select class="w-full"
                name="charity-name"
                required>
          {% for code, charity in charities %}
          <option value="{{ code }}">{{ charity.name }}</option>
          {% endfor %}
        </select>
      </div>
      <h3 class="mb-4">Amount:</h3>
      <div id="donation-amount-choices">
        <div>
          <input name="donation-amount"
                 type="radio"
                 class="hidden peer"
                 id="donation-amount-5"
                 value="5">
          <label for="donation-amount-5">$5</label>
        </div>
        <div>
          <input name="donation-amount"
                 type="radio"
                 class="hidden peer"
                 id="donation-amount-10"
                 value="10">
          <label for="donation-amount-10">$10</label>
        </div>
        <div>
          <input name="donation-amount"
                 type="radio"
                 class="hidden peer"
                 id="donation-amount-15"
                 value="15">
          <label for="donation-amount-15">
            $15</label>
        </div>
      </div>
      <div class="rounded-md border-2 border-slate-300 mb-4">
        <div class="relative flex items-center">
          <img src="/images/icons/dollar-currency-symbol.png" class="absolute ml-4" alt="">
          <input name="donation-amount-other"
                 id="donation-amount-other"
                 type="text"
                 class="donation-amount-other"
                 placeholder="Enter a donation amount">
        </div>
      </div>
      <div class="pt-4 border-t-2 border-t-slate-300">
        <button class="justify-center py-4 w-full font-bold uppercase rounded-md bg-purple-700 text-white enabled:hover:cursor-pointer enabled:hover:drop-shadow-md enabled:hover:bg-purple-600 disabled:bg-slate-400 ease-in-out duration-300"
                disabled
                id="donate-button">Donate</button>
      </div>
    </div>
  </form>
</template>

<script>
export default {
  name: "DonationForm",
  data() {
    return {
      donation: 0,
      donationAmountChosen: '',
      donationAmountPreset: ''
    }
  },
  watch: {
    donationAmountChosen(newAmount) {
      console.log(newAmount)
      this.donation = newAmount
    },
    donationAmountPreset(newAmount) {
      console.log(newAmount)
      this.donation = newAmount
    }
  },
  methods: {
    setDonationAmount: function(e) {
      if (e.target.name === 'donation-amount-voluntary') {
        this.donationAmountPreset = ''
      } else {
        this.donationAmountChosen = ''
      }
    }
  }
}
</script>

<style scoped>

</style>